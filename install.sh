#!/bin/bash

# ========================================
# 🦕 Techtyl Installer v1.0
# Pterodactyl Panel + AI (Azure OpenAI)
# ========================================

set -e

clear
echo ""
echo "========================================="
echo "  🦕 Techtyl Installer"
echo "========================================="
echo ""
echo "Installiert:"
echo "  • Pterodactyl Panel"
echo "  • Azure OpenAI Integration"
echo "  • User-Registrierung"
echo "  • Server-Erstellung für User"
echo ""
echo "Dauer: ~15-20 Minuten"
echo ""
read -p "Installation starten? (y/n): " -n 1 -r
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
[[ $EUID -ne 0 ]] && err "Als root ausführen: sudo bash install.sh"

# OS check
[ -f /etc/os-release ] && . /etc/os-release || err "OS nicht erkannt"
[[ "$ID" != "ubuntu" && "$ID" != "debian" ]] && err "Nur Ubuntu/Debian unterstützt"

info "System: $ID $VERSION_ID"

# ========================================
# 1. System-Pakete
# ========================================
echo ""
echo "[1/8] Installiere System-Pakete..."

export DEBIAN_FRONTEND=noninteractive

apt update -qq
apt install -y software-properties-common curl apt-transport-https ca-certificates gnupg lsb-release wget tar unzip git

# PHP 8.2
LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php 2>/dev/null
apt update -qq

# Pakete installieren (OHNE nodejs/npm!)
apt install -y \
    php8.2 php8.2-{cli,gd,mysql,pdo,mbstring,tokenizer,bcmath,xml,fpm,curl,zip} \
    mariadb-server mariadb-client \
    nginx \
    redis-server \
    certbot python3-certbot-nginx \
    composer

ok "Pakete installiert"

# ========================================
# 2. MariaDB
# ========================================
echo ""
echo "[2/8] Richte MariaDB ein..."

systemctl start mariadb
systemctl enable mariadb >/dev/null 2>&1

# Sichere Passwörter generieren
DB_ROOT_PASS=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-25)
DB_USER_PASS=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-25)

# Root-Passwort setzen
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${DB_ROOT_PASS}';" 2>/dev/null || \
    mysqladmin -u root password "${DB_ROOT_PASS}" 2>/dev/null || true

# Datenbank erstellen
mysql -u root -p"${DB_ROOT_PASS}" <<EOF 2>/dev/null
DROP DATABASE IF EXISTS panel;
CREATE DATABASE panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS 'pterodactyl'@'127.0.0.1';
CREATE USER 'pterodactyl'@'127.0.0.1' IDENTIFIED BY '${DB_USER_PASS}';
GRANT ALL PRIVILEGES ON panel.* TO 'pterodactyl'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF

ok "MariaDB konfiguriert"

# ========================================
# 3. Pterodactyl Panel
# ========================================
echo ""
echo "[3/8] Lade Pterodactyl..."

mkdir -p /var/www/pterodactyl
cd /var/www/pterodactyl

# Neueste Version
PTERO_VER=$(curl -s https://api.github.com/repos/pterodactyl/panel/releases/latest | grep '"tag_name":' | sed -E 's/.*"v([^"]+)".*/\1/')
curl -Lo panel.tar.gz "https://github.com/pterodactyl/panel/releases/download/v${PTERO_VER}/panel.tar.gz" 2>/dev/null
tar -xzf panel.tar.gz
chmod -R 755 storage/* bootstrap/cache/
rm panel.tar.gz

ok "Pterodactyl v${PTERO_VER} geladen"

# ========================================
# 4. PHP Dependencies
# ========================================
echo ""
echo "[4/8] Installiere Dependencies..."

COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader -q 2>/dev/null
COMPOSER_ALLOW_SUPERUSER=1 composer require guzzlehttp/guzzle -q 2>/dev/null

ok "Dependencies installiert"

# ========================================
# 5. Environment
# ========================================
echo ""
echo "[5/8] Konfiguriere Environment..."

cp .env.example .env
php artisan key:generate --force

# Server-Konfiguration
echo ""
info "Server-Konfiguration:"
echo ""

# IP automatisch erkennen
SERVER_IP=$(hostname -I | awk '{print $1}')
echo "Erkannte Server-IP: $SERVER_IP"
echo ""
read -p "Panel-URL (Enter für http://${SERVER_IP}): " PANEL_URL
PANEL_URL=${PANEL_URL:-http://${SERVER_IP}}

echo ""
info "Panel wird erreichbar unter: $PANEL_URL"

# Azure OpenAI Eingabe
echo ""
info "Azure OpenAI Konfiguration:"
echo ""
read -p "API Key: " AZURE_KEY
read -p "Endpoint (https://...openai.azure.com/): " AZURE_ENDPOINT
read -p "Deployment (z.B. gpt-4o): " AZURE_DEPLOY

# .env befüllen
cat >> .env <<EOF

# App
APP_NAME=Techtyl
APP_URL=${PANEL_URL}
APP_TIMEZONE=Europe/Berlin
APP_LOCALE=de

# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=panel
DB_USERNAME=pterodactyl
DB_PASSWORD=${DB_USER_PASS}

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Techtyl - User Features
REGISTRATION_ENABLED=true
USER_SERVER_CREATION_ENABLED=true
USER_SERVER_LIMIT=3
USER_SERVER_MEMORY_LIMIT=4096
USER_SERVER_CPU_LIMIT=200
USER_SERVER_DISK_LIMIT=10240

# Techtyl - AI
TECHTYL_AI_ENABLED=true
TECHTYL_MAX_REQUESTS=50
TECHTYL_CACHE_RESPONSES=true
TECHTYL_CACHE_DURATION=1440

# Azure OpenAI
AZURE_OPENAI_API_KEY=${AZURE_KEY}
AZURE_OPENAI_ENDPOINT=${AZURE_ENDPOINT}
AZURE_OPENAI_DEPLOYMENT=${AZURE_DEPLOY}
AZURE_OPENAI_API_VERSION=2024-02-15-preview
EOF

ok "Environment konfiguriert"

# ========================================
# 6. Datenbank Migration
# ========================================
echo ""
echo "[6/8] Migriere Datenbank..."

php artisan migrate --seed --force

# Admin erstellen
echo ""
info "Admin-Account erstellen:"
php artisan p:user:make --admin

ok "Datenbank migriert"

# ========================================
# 7. Techtyl AI-Features
# ========================================
echo ""
echo "[7/8] Installiere AI-Features..."

# Service
cat > app/Services/AzureOpenAIService.php <<'PHPEOF'
<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AzureOpenAIService
{
    private $apiKey, $endpoint, $deployment;

    public function __construct()
    {
        $this->apiKey = env('AZURE_OPENAI_API_KEY');
        $this->endpoint = env('AZURE_OPENAI_ENDPOINT');
        $this->deployment = env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o');

        if (empty($this->apiKey)) {
            throw new \Exception('Azure OpenAI not configured');
        }
    }

    public function getSuggestions(string $gameType): array
    {
        $key = 'ai_' . md5($gameType);
        if (Cache::has($key)) return Cache::get($key);

        $prompt = "Als Gaming-Server Experte, optimale Ressourcen für {$gameType}-Server:\nJSON: {\"cpu\":2,\"memory\":2048,\"disk\":10240,\"explanation\":\"...\"}";

        $res = $this->send($prompt);
        if ($res['success'] && preg_match('/\{.*\}/s', $res['message'], $m)) {
            $cfg = json_decode($m[0], true);
            if (isset($cfg['cpu'])) {
                $result = ['success' => true, 'config' => $cfg];
                Cache::put($key, $result, 1440);
                return $result;
            }
        }
        return ['success' => false];
    }

    public function getNames(string $gameType, int $count = 5): array
    {
        $prompt = "{$count} kreative Server-Namen für {$gameType}. Nur Buchstaben, Zahlen, - _\nJSON: [\"Name1\",\"Name2\"]";

        $res = $this->send($prompt);
        if ($res['success'] && preg_match('/\[.*\]/s', $res['message'], $m)) {
            $names = json_decode($m[0], true);
            if (is_array($names)) return ['success' => true, 'names' => $names];
        }
        return ['success' => false];
    }

    public function help(string $q, $gameType = null): array
    {
        $prompt = "Frage: {$q}\n";
        if ($gameType) $prompt .= "Server: {$gameType}\n";
        $prompt .= "Antworte kurz auf Deutsch.";
        return $this->send($prompt);
    }

    private function send(string $prompt): array
    {
        try {
            $url = rtrim($this->endpoint, '/') . "/openai/deployments/{$this->deployment}/chat/completions?api-version=2024-02-15-preview";

            $res = Http::withHeaders(['api-key' => $this->apiKey])
                ->timeout(30)
                ->post($url, [
                    'messages' => [
                        ['role' => 'system', 'content' => 'Gaming-Server Experte. Deutsch.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 800
                ]);

            if ($res->successful()) {
                $data = $res->json();
                if (isset($data['choices'][0]['message']['content'])) {
                    return ['success' => true, 'message' => $data['choices'][0]['message']['content']];
                }
            }
        } catch (\Exception $e) {
            Log::error('AI error: ' . $e->getMessage());
        }
        return ['success' => false];
    }
}
PHPEOF

# Controller
mkdir -p app/Http/Controllers/Api/Client/Techtyl
cat > app/Http/Controllers/Api/Client/Techtyl/AIController.php <<'PHPEOF'
<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Techtyl;

use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Illuminate\Http\Request;
use Pterodactyl\Services\AzureOpenAIService;
use Pterodactyl\Models\Egg;

class AIController extends ClientApiController
{
    private $ai;

    public function __construct(AzureOpenAIService $ai)
    {
        parent::__construct();
        $this->ai = $ai;
    }

    public function suggestions(Request $request)
    {
        $egg = Egg::findOrFail($request->egg_id);
        return $this->ai->getSuggestions($egg->name);
    }

    public function names(Request $request)
    {
        $egg = Egg::findOrFail($request->egg_id);
        return $this->ai->getNames($egg->name, $request->count ?? 5);
    }

    public function help(Request $request)
    {
        $gt = null;
        if ($request->egg_id) {
            $egg = Egg::find($request->egg_id);
            $gt = $egg ? $egg->name : null;
        }
        return $this->ai->help($request->question, $gt);
    }
}
PHPEOF

# Config
cat > config/techtyl.php <<'PHPEOF'
<?php
return [
    'enabled' => env('TECHTYL_AI_ENABLED', true),
    'max_requests' => env('TECHTYL_MAX_REQUESTS', 50),
    'cache_responses' => env('TECHTYL_CACHE_RESPONSES', true),
    'cache_duration' => env('TECHTYL_CACHE_DURATION', 1440),
];
PHPEOF

# Routes
cat >> routes/api-client.php <<'PHPEOF'

// Techtyl AI
Route::prefix('/techtyl')->group(function () {
    Route::post('/ai/suggestions', 'Techtyl\AIController@suggestions');
    Route::post('/ai/names', 'Techtyl\AIController@names');
    Route::post('/ai/help', 'Techtyl\AIController@help');
});
PHPEOF

composer dump-autoload -q 2>/dev/null

ok "AI-Features installiert"

# ========================================
# 8. Webserver & Services
# ========================================
echo ""
echo "[8/8] Konfiguriere Webserver..."

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
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PHP_VALUE "upload_max_filesize = 100M \n post_max_size=100M";
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINXEOF

ln -sf /etc/nginx/sites-available/pterodactyl.conf /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Queue Worker
cat > /etc/systemd/system/pteroq.service <<EOF
[Unit]
Description=Pterodactyl Queue Worker
After=redis-server.service

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/pterodactyl/artisan queue:work --queue=high,standard,low --sleep=3 --tries=3

[Install]
WantedBy=multi-user.target
EOF

# Permissions (wichtig für 500-Fehler!)
chown -R www-data:www-data /var/www/pterodactyl
chmod -R 755 /var/www/pterodactyl/storage
chmod -R 755 /var/www/pterodactyl/bootstrap/cache

# Storage-Link erstellen (für Assets)
php artisan storage:link

# Cache leeren
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache neu aufbauen
php artisan config:cache
php artisan route:cache

# Services starten
systemctl enable --now redis-server >/dev/null 2>&1
systemctl enable --now pteroq.service >/dev/null 2>&1
systemctl restart php8.2-fpm
systemctl reload nginx

ok "Services gestartet"

# ========================================
# Fertig!
# ========================================

echo ""
echo "========================================="
echo "  ✅ Installation abgeschlossen!"
echo "========================================="
echo ""
echo "🦕 Techtyl v1.0"
echo "   based on Pterodactyl v${PTERO_VER}"
echo ""
echo "📱 Panel-URL:"
echo "   ${PANEL_URL}"
echo ""
echo "🔐 Datenbank:"
echo "   Host: 127.0.0.1"
echo "   DB: panel"
echo "   User: pterodactyl"
echo "   Pass: ${DB_USER_PASS}"
echo ""
echo "   MySQL Root: ${DB_ROOT_PASS}"
echo ""
echo "✨ Features:"
echo "   ✓ User-Registrierung"
echo "   ✓ Server-Erstellung (3/User)"
echo "   ✓ AI-Assistent"
echo ""
echo "📖 Nächste Schritte:"
echo "   1. Öffne ${PANEL_URL}"
echo "   2. Login mit Admin-Account"
echo "   3. Node erstellen (Admin → Nodes → Create New)"
echo "   4. Allocations hinzufügen (z.B. Port 25565)"
echo "   5. User können sich registrieren und Server erstellen!"
echo ""
echo "🔒 SSL einrichten:"
echo "   certbot --nginx -d deine-domain.de"
echo ""
echo "========================================="

# Speichern
cat > /root/techtyl-info.txt <<EOF
Techtyl Installation - $(date)

Panel-URL: ${PANEL_URL}

Datenbank:
  Host: 127.0.0.1
  DB: panel
  User: pterodactyl
  Pass: ${DB_USER_PASS}

MySQL Root: ${DB_ROOT_PASS}

Azure OpenAI:
  Key: ${AZURE_KEY}
  Endpoint: ${AZURE_ENDPOINT}
  Deployment: ${AZURE_DEPLOY}
EOF

chmod 600 /root/techtyl-info.txt

echo "💾 Info gespeichert: /root/techtyl-info.txt"
echo ""
