#!/bin/bash

# ========================================
# Techtyl Installer v1.3.0
# Pterodactyl Panel + AI (Azure OpenAI)
# ========================================

set -e

clear
echo ""
echo "========================================="
echo "  Techtyl Installer"
echo "========================================="
echo ""
echo "Installing:"
echo "  • Pterodactyl Panel"
echo "  • Azure OpenAI Integration"
echo ""
echo "Duration: ~15-20 minutes"
echo ""
read -p "Start installation? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    exit 1
fi

# Colors
G='\033[0;32m'
R='\033[0;31m'
Y='\033[1;33m'
B='\033[0;34m'
NC='\033[0m'

ok() { echo -e "${G}✓${NC} $1"; }
err() { echo -e "${R}✗${NC} $1"; exit 1; }
warn() { echo -e "${Y}!${NC} $1"; }
info() { echo -e "${B}➜${NC} $1"; }

# Root check
[[ $EUID -ne 0 ]] && err "Run as root: sudo bash install.sh"

# OS check
[ -f /etc/os-release ] && . /etc/os-release || err "OS not detected"
[[ "$ID" != "ubuntu" && "$ID" != "debian" ]] && err "Only Ubuntu/Debian supported"

info "System: $ID $VERSION_ID"

# Detect best PHP version
if [[ "$VERSION_ID" == "24.04" ]] || [[ "$VERSION_ID" == "12" ]]; then
    PHP_VER="8.3"
elif [[ "$VERSION_ID" == "22.04" ]] || [[ "$VERSION_ID" == "11" ]]; then
    PHP_VER="8.2"
else
    PHP_VER="8.2"
fi

info "Using PHP $PHP_VER"

# Pre-Installation Check
echo ""
info "Checking existing installation..."

FRESH_INSTALL=true

if [ -f "/var/www/pterodactyl/artisan" ]; then
    warn "Pterodactyl already installed"
    read -p "Continue anyway? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
    FRESH_INSTALL=false
fi

# ========================================
# 1. System Preparation
# ========================================
echo ""
echo "[1/8] Preparing system..."

export DEBIAN_FRONTEND=noninteractive

apt update -qq
apt install -y software-properties-common curl apt-transport-https ca-certificates gnupg

if [[ "$PHP_VER" == "8.3" ]]; then
    add-apt-repository ppa:ondrej/php -y
fi

apt update -qq

ok "System prepared"

# ========================================
# 2. Install Packages
# ========================================
echo ""
echo "[2/8] Installing packages..."

apt install -y \
    php${PHP_VER} php${PHP_VER}-{cli,gd,mysql,pdo,mbstring,tokenizer,bcmath,xml,fpm,curl,zip,intl} \
    mariadb-server mariadb-client \
    nginx \
    redis-server \
    tar unzip git composer

ok "Packages installed (PHP ${PHP_VER})"

# ========================================
# 3. MariaDB Setup
# ========================================
echo ""
echo "[3/8] Setting up MariaDB..."

systemctl start mariadb

# Generate passwords
DB_ROOT_PASS=$(openssl rand -base64 32)
DB_USER_PASS=$(openssl rand -base64 32)

# Check if database exists
DB_EXISTS=false
if timeout 2 mysql -e "USE panel;" 2>/dev/null; then
    DB_EXISTS=true
    warn "Database 'panel' already exists"
fi

# Set root password if not set
if timeout 2 mysql -u root -e "SELECT 1;" >/dev/null 2>&1; then
    mysql -u root <<MYSQL_ROOT
ALTER USER 'root'@'localhost' IDENTIFIED BY '${DB_ROOT_PASS}';
FLUSH PRIVILEGES;
MYSQL_ROOT
    ok "Root password set"
fi

# Create database and user
if [ "$DB_EXISTS" = false ]; then
    mysql -u root -p"${DB_ROOT_PASS}" <<MYSQL_DB
CREATE DATABASE panel;
CREATE USER 'pterodactyl'@'127.0.0.1' IDENTIFIED BY '${DB_USER_PASS}';
GRANT ALL PRIVILEGES ON panel.* TO 'pterodactyl'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
MYSQL_DB
    ok "Database created"
else
    ok "Using existing database"
fi

# ========================================
# 4. Pterodactyl Download
# ========================================
echo ""
echo "[4/8] Downloading Pterodactyl..."

mkdir -p /var/www/pterodactyl
cd /var/www/pterodactyl

if [ "$FRESH_INSTALL" = true ]; then
    PTERO_VER=$(curl -s https://api.github.com/repos/pterodactyl/panel/releases/latest | grep '"tag_name":' | sed -E 's/.*"v([^"]+)".*/\1/')
    curl -Lo panel.tar.gz "https://github.com/pterodactyl/panel/releases/download/v${PTERO_VER}/panel.tar.gz" 2>/dev/null
    tar -xzf panel.tar.gz
    chmod -R 755 storage/* bootstrap/cache/
    rm panel.tar.gz

    ok "Pterodactyl v${PTERO_VER} downloaded"
else
    PTERO_VER="existing"
    ok "Using existing Pterodactyl installation"
fi

# ========================================
# 5. Environment Configuration
# ========================================
echo ""
echo "[5/8] Configuring environment..."

if [ ! -f ".env" ] || [ "$FRESH_INSTALL" = true ]; then
    cp .env.example .env

    composer install --no-dev --optimize-autoloader -q

    php artisan key:generate --force

    # Server configuration
    echo ""
    info "Server configuration:"
    echo ""

    SERVER_IP=$(hostname -I | awk '{print $1}')
    echo "Detected server IP: $SERVER_IP"
    echo ""
    read -p "Panel URL (Enter for http://${SERVER_IP}): " PANEL_URL
    PANEL_URL=${PANEL_URL:-http://${SERVER_IP}}

    # Validate and fix URL format
    if [[ -n "$PANEL_URL" ]] && [[ ! "$PANEL_URL" =~ ^https?:// ]]; then
        warn "URL without http:// detected - fixing..."
        PANEL_URL="http://${PANEL_URL}"
    fi

    echo ""
    info "Panel will be accessible at: $PANEL_URL"

    # Configure .env
    sed -i "s|APP_URL=.*|APP_URL=${PANEL_URL}|" .env
    sed -i "s|DB_HOST=.*|DB_HOST=127.0.0.1|" .env
    sed -i "s|DB_PORT=.*|DB_PORT=3306|" .env
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=panel|" .env
    sed -i "s|DB_USERNAME=.*|DB_USERNAME=pterodactyl|" .env
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_USER_PASS}|" .env

    ok "Environment configured"
else
    ok "Using existing .env configuration"
fi

# ========================================
# 6. Database Migration
# ========================================
echo ""
echo "[6/8] Running database migration..."

php artisan migrate --seed --force

ok "Database migrated"

# ========================================
# 7. Admin User Creation
# ========================================
echo ""
echo "[7/8] Creating admin user..."

if [ "$FRESH_INSTALL" = true ]; then
    echo ""
    info "Admin account:"
    echo ""
    read -p "Email: " ADMIN_EMAIL
    read -p "Username: " ADMIN_USERNAME
    read -sp "Password: " ADMIN_PASSWORD
    echo ""

    php artisan p:user:make \
        --email="${ADMIN_EMAIL}" \
        --username="${ADMIN_USERNAME}" \
        --name-first="Admin" \
        --name-last="User" \
        --password="${ADMIN_PASSWORD}" \
        --admin=1

    ok "Admin user created"
else
    ok "Skipped (existing installation)"
fi

# ========================================
# 8. Techtyl AI Integration
# ========================================
echo ""
echo "[8/8] Installing Techtyl AI features..."

# Azure OpenAI Service
mkdir -p app/Services

cat > app/Services/AzureOpenAIService.php <<'PHPEOF'
<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AzureOpenAIService
{
    private string $apiKey;
    private string $endpoint;
    private string $deployment;
    private string $apiVersion;

    public function __construct()
    {
        $this->apiKey = config('techtyl.azure_openai.api_key');
        $this->endpoint = config('techtyl.azure_openai.endpoint');
        $this->deployment = config('techtyl.azure_openai.deployment');
        $this->apiVersion = config('techtyl.azure_openai.api_version');
    }

    public function getSuggestions(string $gameType): array
    {
        $cacheKey = 'ai_suggestions_' . md5($gameType);

        if (config('techtyl.cache_responses') && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $prompt = "As a game server expert, provide optimal resource recommendations for a {$gameType} game server. Respond in JSON format with: {\"cpu\":number,\"memory\":number_in_mb,\"disk\":number_in_mb,\"explanation\":\"brief explanation\"}";

        $response = $this->sendRequest($prompt);

        if (config('techtyl.cache_responses')) {
            Cache::put($cacheKey, $response, now()->addMinutes(config('techtyl.cache_duration')));
        }

        return $response;
    }

    public function getNames(string $gameType, int $count = 5): array
    {
        $prompt = "Generate {$count} creative and suitable server names for a {$gameType} game server. Respond in JSON format: {\"names\":[\"name1\",\"name2\",...]}";
        return $this->sendRequest($prompt);
    }

    public function getHelp(string $question, string $context = ''): array
    {
        $prompt = "As a game server expert, answer this question: {$question}";
        if ($context) {
            $prompt .= " Context: {$context}";
        }
        return $this->sendRequest($prompt);
    }

    private function sendRequest(string $prompt): array
    {
        $url = rtrim($this->endpoint, '/') . "/openai/deployments/{$this->deployment}/chat/completions?api-version={$this->apiVersion}";

        try {
            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful game server expert.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 500,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';

                $jsonContent = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $jsonContent;
                }

                return ['content' => $content];
            }

            return ['error' => 'AI request failed: ' . $response->status()];
        } catch (\Exception $e) {
            return ['error' => 'AI request exception: ' . $e->getMessage()];
        }
    }
}
PHPEOF

ok "AzureOpenAIService created"

# AI Controller
mkdir -p app/Http/Controllers/Api/Client/Techtyl

cat > app/Http/Controllers/Api/Client/Techtyl/AIController.php <<'PHPEOF'
<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Techtyl;

use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Services\AzureOpenAIService;
use Pterodactyl\Models\Egg;

class AIController extends ClientApiController
{
    private AzureOpenAIService $ai;

    public function __construct(AzureOpenAIService $ai)
    {
        parent::__construct();
        $this->ai = $ai;
    }

    public function suggestions(Request $request)
    {
        $validated = $request->validate([
            'egg_id' => 'required|integer|exists:eggs,id',
        ]);

        $egg = Egg::findOrFail($validated['egg_id']);
        $suggestions = $this->ai->getSuggestions($egg->name);

        return response()->json($suggestions);
    }

    public function names(Request $request)
    {
        $validated = $request->validate([
            'egg_id' => 'required|integer|exists:eggs,id',
            'count' => 'sometimes|integer|min:1|max:10',
        ]);

        $egg = Egg::findOrFail($validated['egg_id']);
        $count = $validated['count'] ?? 5;
        $names = $this->ai->getNames($egg->name, $count);

        return response()->json($names);
    }

    public function help(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'context' => 'sometimes|string|max:1000',
        ]);

        $help = $this->ai->getHelp($validated['question'], $validated['context'] ?? '');

        return response()->json($help);
    }
}
PHPEOF

ok "AIController created"

# Techtyl Config
mkdir -p config

cat > config/techtyl.php <<'PHPEOF'
<?php

return [
    'azure_openai' => [
        'api_key' => env('AZURE_OPENAI_API_KEY'),
        'endpoint' => env('AZURE_OPENAI_ENDPOINT'),
        'deployment' => env('AZURE_OPENAI_DEPLOYMENT'),
        'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-02-15-preview'),
    ],

    'ai_enabled' => env('TECHTYL_AI_ENABLED', true),
    'max_requests' => env('TECHTYL_MAX_REQUESTS', 50),
    'cache_responses' => env('TECHTYL_CACHE_RESPONSES', true),
    'cache_duration' => env('TECHTYL_CACHE_DURATION', 1440),
];
PHPEOF

ok "Techtyl config created"

# AI Routes
if [ -f "routes/api-client.php" ]; then
    if ! grep -q "/techtyl" routes/api-client.php; then
        cat >> routes/api-client.php <<'PHPEOF'

// Techtyl AI Routes
Route::prefix('/techtyl')->group(function () {
    Route::post('/ai/suggestions', [\Pterodactyl\Http\Controllers\Api\Client\Techtyl\AIController::class, 'suggestions']);
    Route::post('/ai/names', [\Pterodactyl\Http\Controllers\Api\Client\Techtyl\AIController::class, 'names']);
    Route::post('/ai/help', [\Pterodactyl\Http\Controllers\Api\Client\Techtyl\AIController::class, 'help']);
});
PHPEOF
        ok "AI routes added"
    fi
fi

ok "AI integration complete"

# ========================================
# Webserver & Services
# ========================================
echo ""
info "Configuring webserver and services..."

# Nginx
cat > /etc/nginx/sites-available/pterodactyl.conf <<'NGINXEOF'
server {
    listen 80 default_server;
    server_name _;
    root /var/www/pterodactyl/public;
    index index.php;

    client_max_body_size 100m;
    client_body_timeout 120s;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php__PHP_VER__-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PHP_VALUE "upload_max_filesize = 100M \n post_max_size=100M";
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINXEOF

# Replace PHP version placeholder
sed -i "s/__PHP_VER__/${PHP_VER}/g" /etc/nginx/sites-available/pterodactyl.conf

# Enable site
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/pterodactyl.conf /etc/nginx/sites-enabled/pterodactyl.conf

ok "Nginx configured"

# pteroq Service
cat > /etc/systemd/system/pteroq.service <<EOF
[Unit]
Description=Pterodactyl Queue Worker
After=redis-server.service

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/pterodactyl/artisan queue:work --queue=high,standard,low --sleep=3 --tries=3
StartLimitInterval=180
StartLimitBurst=30
RestartSec=5s

[Install]
WantedBy=multi-user.target
EOF

ok "pteroq service created"

# Permissions (CRITICAL for avoiding 500 errors)
info "Setting ownership and permissions..."

chown -R www-data:www-data /var/www/pterodactyl

find /var/www/pterodactyl -type d -exec chmod 755 {} \;
find /var/www/pterodactyl -type f -exec chmod 644 {} \;

chmod -R 755 /var/www/pterodactyl/storage
chmod -R 755 /var/www/pterodactyl/bootstrap/cache
chmod +x /var/www/pterodactyl/artisan

ok "Permissions set correctly"

# Storage Link
php artisan storage:link

# Clear and rebuild cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Enable and start services
systemctl enable --now mariadb redis-server nginx php${PHP_VER}-fpm pteroq

ok "Services enabled and started"

# ========================================
# Installation Complete
# ========================================

clear
echo ""
echo "========================================="
echo "  ✓ Techtyl Installation Complete!"
echo "========================================="
echo ""
echo "Access your panel:"
echo "  • URL: ${PANEL_URL}"
echo "  • Admin: ${ADMIN_EMAIL}"
echo ""
echo "Next steps:"
echo "  1. Login at: ${PANEL_URL}/auth/login"
echo "  2. Create a Node (Admin → Nodes)"
echo "  3. Add Allocations (ports)"
echo "  4. Create your first server!"
echo ""
echo "Logs:"
echo "  • Panel: /var/www/pterodactyl/storage/logs/laravel.log"
echo "  • Nginx: /var/log/nginx/error.log"
echo ""
echo "Services:"
echo "  • systemctl status nginx php${PHP_VER}-fpm pteroq"
echo ""

# Save credentials
cat > /root/techtyl-info.txt <<EOF
Techtyl Installation Info
=========================

Panel URL: ${PANEL_URL}

Admin Account:
  Email: ${ADMIN_EMAIL}
  Username: ${ADMIN_USERNAME}

Database:
  Name: panel
  User: pterodactyl
  Password: ${DB_USER_PASS}
  Root Password: ${DB_ROOT_PASS}

Azure OpenAI:
  Key: ${AZURE_KEY}
  Endpoint: ${AZURE_ENDPOINT}
  Deployment: ${AZURE_DEPLOY}
EOF

chmod 600 /root/techtyl-info.txt

echo "💾 Info saved: /root/techtyl-info.txt"
echo ""
