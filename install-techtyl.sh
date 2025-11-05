#!/bin/bash

# ========================================
# Techtyl - Pterodactyl mit AI
# One-Click Installation
# ========================================

set -e

echo "========================================="
echo "  Techtyl - Pterodactyl Panel mit AI"
echo "========================================="
echo ""
echo "Dieses Script installiert:"
echo "  ✓ Pterodactyl Panel (neueste Version)"
echo "  ✓ AI-Features (Azure OpenAI)"
echo "  ✓ Automatische Konfiguration"
echo ""
echo "Dauer: ~15-20 Minuten"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}!${NC} $1"
}

print_info() {
    echo -e "${BLUE}➜${NC} $1"
}

# Root-Check
if [[ $EUID -ne 0 ]]; then
   print_error "Dieses Script muss als root ausgeführt werden!"
   echo "Versuche: sudo bash install-techtyl.sh"
   exit 1
fi

# OS-Check
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    OS_VER=$VERSION_ID
else
    print_error "Kann OS nicht erkennen!"
    exit 1
fi

print_info "Erkanntes System: $OS $OS_VER"

# ========================================
# 1. System-Updates & Dependencies
# ========================================
echo ""
echo "[1/8] Installiere System-Dependencies..."

if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
    apt update -y
    apt upgrade -y
    apt install -y software-properties-common curl apt-transport-https ca-certificates gnupg

    # PHP 8.2 Repository
    add-apt-repository ppa:ondrej/php -y
    apt update -y

    # PHP & Extensions
    apt install -y php8.2 php8.2-{common,cli,gd,mysql,mbstring,bcmath,xml,fpm,curl,zip}

    # Weitere Dependencies
    apt install -y nginx tar unzip git redis-server mariadb-server composer

elif [ "$OS" = "centos" ] || [ "$OS" = "rhel" ]; then
    dnf install -y epel-release
    dnf install -y php php-{common,fpm,cli,json,mysqlnd,mcrypt,gd,mbstring,pdo,zip,bcmath,dom,opcache}
    dnf install -y nginx mariadb mariadb-server redis composer git
else
    print_error "Nicht unterstütztes OS: $OS"
    exit 1
fi

print_success "Dependencies installiert"

# ========================================
# 2. Datenbank einrichten
# ========================================
echo ""
echo "[2/8] Richte Datenbank ein..."

# MariaDB starten
systemctl start mariadb
systemctl enable mariadb

# Datenbank-Credentials generieren
DB_PASSWORD=$(openssl rand -base64 32)
DB_ROOT_PASSWORD=$(openssl rand -base64 32)

# MySQL root-Passwort setzen (falls noch nicht)
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${DB_ROOT_PASSWORD}';" 2>/dev/null || true

# Pterodactyl Datenbank erstellen
mysql -u root -p"${DB_ROOT_PASSWORD}" <<EOF
CREATE DATABASE IF NOT EXISTS panel;
CREATE USER IF NOT EXISTS 'pterodactyl'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON panel.* TO 'pterodactyl'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF

print_success "Datenbank erstellt"

# ========================================
# 3. Pterodactyl Panel herunterladen
# ========================================
echo ""
echo "[3/8] Lade Pterodactyl Panel herunter..."

mkdir -p /var/www/pterodactyl
cd /var/www/pterodactyl

# Neueste Pterodactyl Version
PTERODACTYL_VERSION=$(curl -s https://api.github.com/repos/pterodactyl/panel/releases/latest | grep '"tag_name":' | sed -E 's/.*"([^"]+)".*/\1/')
curl -Lo panel.tar.gz "https://github.com/pterodactyl/panel/releases/latest/download/panel.tar.gz"
tar -xzvf panel.tar.gz
chmod -R 755 storage/* bootstrap/cache/

print_success "Pterodactyl $PTERODACTYL_VERSION heruntergeladen"

# ========================================
# 4. Composer Dependencies
# ========================================
echo ""
echo "[4/8] Installiere PHP Dependencies..."

cd /var/www/pterodactyl
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader

# Guzzle für Azure OpenAI
COMPOSER_ALLOW_SUPERUSER=1 composer require guzzlehttp/guzzle

print_success "Dependencies installiert"

# ========================================
# 5. Environment konfigurieren
# ========================================
echo ""
echo "[5/8] Konfiguriere Environment..."

cp .env.example .env

# App Key generieren
php artisan key:generate --force

# Azure OpenAI Credentials abfragen
echo ""
print_info "Azure OpenAI Konfiguration:"
read -p "Azure OpenAI API Key: " AZURE_KEY
read -p "Azure OpenAI Endpoint: " AZURE_ENDPOINT
read -p "Azure OpenAI Deployment (z.B. gpt-4o): " AZURE_DEPLOYMENT

# .env konfigurieren
cat >> .env <<EOF

# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=panel
DB_USERNAME=pterodactyl
DB_PASSWORD=${DB_PASSWORD}

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Techtyl AI Features
TECHTYL_AI_ENABLED=true
TECHTYL_MAX_REQUESTS=50
TECHTYL_CACHE_RESPONSES=true
TECHTYL_CACHE_DURATION=1440

# Azure OpenAI
AZURE_OPENAI_API_KEY=${AZURE_KEY}
AZURE_OPENAI_ENDPOINT=${AZURE_ENDPOINT}
AZURE_OPENAI_DEPLOYMENT=${AZURE_DEPLOYMENT}
AZURE_OPENAI_API_VERSION=2024-02-15-preview
EOF

print_success "Environment konfiguriert"

# ========================================
# 6. Techtyl AI-Features installieren
# ========================================
echo ""
echo "[6/8] Installiere Techtyl AI-Features..."

# Services hinzufügen
mkdir -p /var/www/pterodactyl/app/Services

cat > /var/www/pterodactyl/app/Services/AzureOpenAIService.php <<'PHPEOF'
<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AzureOpenAIService
{
    private string $apiKey;
    private string $endpoint;
    private string $deployment;
    private string $apiVersion;

    public function __construct()
    {
        $this->apiKey = env('AZURE_OPENAI_API_KEY');
        $this->endpoint = env('AZURE_OPENAI_ENDPOINT');
        $this->deployment = env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o');
        $this->apiVersion = env('AZURE_OPENAI_API_VERSION', '2024-02-15-preview');

        if (empty($this->apiKey) || empty($this->endpoint)) {
            throw new \Exception('Azure OpenAI credentials not configured.');
        }
    }

    public function getServerConfigSuggestions(string $gameType, array $requirements = []): array
    {
        $cacheKey = 'ai_config_' . md5($gameType . serialize($requirements));

        if (config('techtyl.cache_responses', true)) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $prompt = "Als Serverexperte, schlage optimale Konfigurationen für einen {$gameType}-Server vor.\n\n";

        if (!empty($requirements)) {
            $prompt .= "Anforderungen:\n";
            foreach ($requirements as $key => $value) {
                $prompt .= "- {$key}: {$value}\n";
            }
        }

        $prompt .= "\nBitte gib Empfehlungen für:\n";
        $prompt .= "1. CPU-Kerne (1-8)\n";
        $prompt .= "2. RAM in MB (512-16384)\n";
        $prompt .= "3. Disk Space in MB (1024-102400)\n\n";
        $prompt .= "Antwort NUR als valides JSON:\n";
        $prompt .= '{"cpu": 2, "memory": 2048, "disk": 10240, "explanation": "Begründung"}';

        $response = $this->sendMessage($prompt);

        if ($response['success']) {
            try {
                if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response['message'], $matches)) {
                    $config = json_decode($matches[0], true);
                    if ($config && isset($config['cpu'])) {
                        $result = ['success' => true, 'config' => $config];

                        if (config('techtyl.cache_responses', true)) {
                            Cache::put($cacheKey, $result, config('techtyl.cache_duration', 1440));
                        }

                        return $result;
                    }
                }
            } catch (\Exception $e) {
                Log::error('AI parsing failed', ['error' => $e->getMessage()]);
            }
        }

        return ['success' => false, 'error' => 'Could not generate configuration'];
    }

    public function suggestServerNames(string $gameType, int $count = 5): array
    {
        $prompt = "Generiere {$count} kreative Namen für einen {$gameType}-Server.\n";
        $prompt .= "Nur Buchstaben, Zahlen, - und _. 3-30 Zeichen.\n";
        $prompt .= "Antwort als JSON-Array: [\"Name1\", \"Name2\", ...]";

        $response = $this->sendMessage($prompt);

        if ($response['success']) {
            try {
                if (preg_match('/\[[\s\S]*?\]/s', $response['message'], $matches)) {
                    $names = json_decode($matches[0], true);
                    if ($names && is_array($names)) {
                        return ['success' => true, 'names' => $names];
                    }
                }
            } catch (\Exception $e) {
                Log::error('Name parsing failed', ['error' => $e->getMessage()]);
            }
        }

        return ['success' => false, 'error' => 'Could not generate names'];
    }

    public function helpWithQuestion(string $question, string $gameType = null): array
    {
        $prompt = "Frage eines Server-Administrators:\n\n{$question}\n\n";

        if ($gameType) {
            $prompt .= "Server-Typ: {$gameType}\n\n";
        }

        $prompt .= "Antworte auf Deutsch, präzise und hilfreich.";

        return $this->sendMessage($prompt);
    }

    private function sendMessage(string $prompt): array
    {
        try {
            $url = "{$this->endpoint}/openai/deployments/{$this->deployment}/chat/completions?api-version={$this->apiVersion}";

            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($url, [
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Du bist ein Experte für Gaming-Server und Server-Verwaltung. Antworte auf Deutsch.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['choices'][0]['message']['content'])) {
                    return [
                        'success' => true,
                        'message' => $data['choices'][0]['message']['content']
                    ];
                }
            }

            return ['success' => false, 'error' => 'API error'];
        } catch (\Exception $e) {
            Log::error('Azure OpenAI error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
PHPEOF

# Controller hinzufügen
mkdir -p /var/www/pterodactyl/app/Http/Controllers/Api/Client/Techtyl

cat > /var/www/pterodactyl/app/Http/Controllers/Api/Client/Techtyl/AIController.php <<'PHPEOF'
<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Techtyl;

use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Illuminate\Http\Request;
use Pterodactyl\Services\AzureOpenAIService;
use Pterodactyl\Models\Egg;

class AIController extends ClientApiController
{
    protected AzureOpenAIService $aiService;

    public function __construct(AzureOpenAIService $aiService)
    {
        parent::__construct();
        $this->aiService = $aiService;
    }

    public function getSuggestions(Request $request)
    {
        $request->validate([
            'egg_id' => 'required|integer',
            'players' => 'nullable|integer|min:1',
        ]);

        $egg = Egg::findOrFail($request->egg_id);

        $requirements = [];
        if ($request->has('players')) {
            $requirements['players'] = $request->players;
        }

        return $this->aiService->getServerConfigSuggestions($egg->name, $requirements);
    }

    public function getNames(Request $request)
    {
        $request->validate([
            'egg_id' => 'required|integer',
            'count' => 'nullable|integer|min:1|max:10',
        ]);

        $egg = Egg::findOrFail($request->egg_id);

        return $this->aiService->suggestServerNames($egg->name, $request->count ?? 5);
    }

    public function askQuestion(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'egg_id' => 'nullable|integer',
        ]);

        $gameType = null;
        if ($request->has('egg_id')) {
            $egg = Egg::find($request->egg_id);
            if ($egg) {
                $gameType = $egg->name;
            }
        }

        return $this->aiService->helpWithQuestion($request->question, $gameType);
    }
}
PHPEOF

# Config hinzufügen
cat > /var/www/pterodactyl/config/techtyl.php <<'PHPEOF'
<?php

return [
    'enabled' => env('TECHTYL_AI_ENABLED', true),
    'max_requests_per_user' => env('TECHTYL_MAX_REQUESTS', 50),
    'cache_responses' => env('TECHTYL_CACHE_RESPONSES', true),
    'cache_duration' => env('TECHTYL_CACHE_DURATION', 1440),
];
PHPEOF

# Routes hinzufügen
cat >> /var/www/pterodactyl/routes/api-client.php <<'PHPEOF'

// Techtyl AI Routes
Route::group(['prefix' => '/techtyl'], function () {
    Route::post('/ai/suggestions', 'Techtyl\AIController@getSuggestions');
    Route::post('/ai/names', 'Techtyl\AIController@getNames');
    Route::post('/ai/help', 'Techtyl\AIController@askQuestion');
});
PHPEOF

print_success "AI-Features installiert"

# ========================================
# 7. Datenbank migrieren & Setup
# ========================================
echo ""
echo "[7/8] Richte Pterodactyl ein..."

cd /var/www/pterodactyl

# Cache leeren
php artisan config:clear
php artisan cache:clear

# Migrations ausführen
php artisan migrate --seed --force

# Admin-User erstellen
echo ""
print_info "Erstelle Admin-Account:"
php artisan p:user:make

# Permissions
chown -R www-data:www-data /var/www/pterodactyl/*

print_success "Pterodactyl eingerichtet"

# ========================================
# 8. Webserver konfigurieren
# ========================================
echo ""
echo "[8/8] Konfiguriere Webserver..."

# Nginx Config
cat > /etc/nginx/sites-available/pterodactyl.conf <<'NGINXEOF'
server {
    listen 80;
    server_name _;
    root /var/www/pterodactyl/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINXEOF

ln -sf /etc/nginx/sites-available/pterodactyl.conf /etc/nginx/sites-enabled/pterodactyl.conf
rm -f /etc/nginx/sites-enabled/default

# Services starten
systemctl restart php8.2-fpm
systemctl restart nginx
systemctl restart redis

# Systemd Service für Queue Worker
cat > /etc/systemd/system/pteroq.service <<'SERVICEEOF'
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
SERVICEEOF

systemctl enable pteroq.service
systemctl start pteroq.service

print_success "Webserver konfiguriert"

# ========================================
# Installation abgeschlossen!
# ========================================
echo ""
echo "========================================="
echo "  ✅ Installation abgeschlossen!"
echo "========================================="
echo ""
echo "🎉 Techtyl ist jetzt bereit!"
echo ""
echo "📝 Wichtige Informationen:"
echo ""
echo "Panel URL: http://$(hostname -I | awk '{print $1}')"
echo ""
echo "Datenbank:"
echo "  - Host: 127.0.0.1"
echo "  - Datenbank: panel"
echo "  - User: pterodactyl"
echo "  - Passwort: ${DB_PASSWORD}"
echo ""
echo "MySQL Root-Passwort: ${DB_ROOT_PASSWORD}"
echo ""
echo "⚠️  WICHTIG: Sichere diese Credentials!"
echo ""
echo "📖 Nächste Schritte:"
echo "  1. Öffne Panel-URL im Browser"
echo "  2. Logge dich mit Admin-Account ein"
echo "  3. Erstelle einen neuen Server"
echo "  4. Nutze die AI-Features:"
echo "     - ✨ AI-Empfehlungen"
echo "     - 🏷️ Namen vorschlagen"
echo "     - 💬 Fragen stellen"
echo ""
echo "🔒 SSL/HTTPS einrichten (empfohlen):"
echo "  apt install certbot python3-certbot-nginx"
echo "  certbot --nginx -d deine-domain.de"
echo ""
echo "📚 Dokumentation: https://github.com/theredstonee/Techtyl"
echo ""
echo "========================================="

# Credentials speichern
cat > /root/techtyl-credentials.txt <<EOF
Techtyl Installation - $(date)

Panel URL: http://$(hostname -I | awk '{print $1}')

Datenbank:
  Host: 127.0.0.1
  Datenbank: panel
  User: pterodactyl
  Passwort: ${DB_PASSWORD}

MySQL Root: ${DB_ROOT_PASSWORD}

Azure OpenAI:
  API Key: ${AZURE_KEY}
  Endpoint: ${AZURE_ENDPOINT}
  Deployment: ${AZURE_DEPLOYMENT}
EOF

print_success "Credentials gespeichert in: /root/techtyl-credentials.txt"
echo ""
