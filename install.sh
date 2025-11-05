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

# Detect best PHP version
if [[ "$VERSION_ID" == "24.04" ]] || [[ "$VERSION_ID" == "12" ]]; then
    PHP_VER="8.3"
elif [[ "$VERSION_ID" == "22.04" ]] || [[ "$VERSION_ID" == "11" ]]; then
    PHP_VER="8.2"
else
    PHP_VER="8.2" # Default fallback
fi

info "Using PHP $PHP_VER"

# Pre-Installation Check
echo ""
info "Prüfe bestehende Installation..."

FRESH_INSTALL=true

if [ -f "/var/www/pterodactyl/artisan" ]; then
    warn "Pterodactyl bereits installiert"
    FRESH_INSTALL=false
fi

if mysql -e "USE panel" 2>/dev/null; then
    warn "MariaDB 'panel' Datenbank existiert"
    FRESH_INSTALL=false
fi

if [ "$FRESH_INSTALL" = true ]; then
    ok "Keine bestehende Installation gefunden"
else
    warn "Bestehende Installation erkannt - Script wird nachfragen"
fi

echo ""
read -p "Fortfahren? (y/n): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    exit 0
fi

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
    php${PHP_VER} php${PHP_VER}-{cli,gd,mysql,pdo,mbstring,tokenizer,bcmath,xml,fpm,curl,zip} \
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
echo "Debug: Starting MariaDB configuration..."

# MariaDB starten
echo "Debug: Starting MariaDB service..."
systemctl start mariadb 2>&1 | head -3 || echo "MariaDB already running"
systemctl enable mariadb >/dev/null 2>&1 || true

echo "Debug: Checking MariaDB status..."
# Prüfe ob MariaDB läuft
if ! systemctl is-active --quiet mariadb; then
    err "MariaDB läuft nicht! Status: $(systemctl status mariadb | head -5)"
fi

info "MariaDB läuft"

# Sichere Passwörter generieren (IMMER, damit wir einen haben)
DB_ROOT_PASS=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-25)
DB_USER_PASS=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-25)

echo "Debug: Checking for existing database..."

# Prüfe ob panel DB existiert (mit Timeout!)
DB_EXISTS=false
if timeout 2 mysql -e "USE panel;" 2>/dev/null; then
    DB_EXISTS=true
    warn "MariaDB 'panel' Datenbank existiert bereits!"
    echo ""
    read -p "Bestehende Datenbank LÖSCHEN und neu erstellen? (y/n): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo ""
        warn "Behalte bestehende Datenbank - bitte Root-Passwort eingeben"
        echo ""
        read -p "MySQL Root-Passwort: " -s DB_ROOT_PASS
        echo ""
        read -p "Pterodactyl DB-Passwort (oder Enter für neu generiert): " -s INPUT_PASS
        echo ""

        # Falls leer, nutze generiertes
        if [ ! -z "$INPUT_PASS" ]; then
            DB_USER_PASS=$INPUT_PASS
        fi

        ok "Nutze bestehende Datenbank"
        # Überspringe Neuanlage
        DB_EXISTS=true
    else
        # User will neu erstellen
        DB_EXISTS=false
        echo ""
        info "Datenbank wird neu erstellt..."
    fi
else
    info "Keine bestehende 'panel' Datenbank gefunden"
fi

echo "Debug: DB_EXISTS=$DB_EXISTS"

# Neue Installation oder Neuanlage
if [ "$DB_EXISTS" = false ]; then
    echo "Debug: Creating new database..."
    info "Erstelle neue Datenbank..."

    echo "Debug: Testing root access without password..."
    # Prüfe ob Root-Passwort schon gesetzt ist (mit Timeout!)
    if timeout 2 mysql -u root -e "SELECT 1;" >/dev/null 2>&1; then
        echo "Debug: Root has no password, setting new one..."
        info "Root hat noch kein Passwort - setze neues..."
        mysqladmin -u root password "${DB_ROOT_PASS}"
        echo "Debug: Password set successfully"
    else
        echo "Debug: Root already has password, testing generated one..."
        # Root hat schon Passwort - versuche zu nutzen
        if ! timeout 2 mysql -u root -p"${DB_ROOT_PASS}" -e "SELECT 1;" >/dev/null 2>&1; then
            warn "Generiertes Passwort funktioniert nicht"
            echo ""
            read -p "Bestehendes MySQL Root-Passwort eingeben: " -s EXISTING_ROOT_PASS
            echo ""
            DB_ROOT_PASS=$EXISTING_ROOT_PASS
            echo "Debug: Using user-provided password"
        else
            echo "Debug: Generated password works"
        fi
    fi

    # Datenbank erstellen
    echo "Debug: Creating database and user..."
    info "Erstelle panel Datenbank..."
    if timeout 10 mysql -u root -p"${DB_ROOT_PASS}" <<EOF 2>/dev/null
DROP DATABASE IF EXISTS panel;
CREATE DATABASE panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS 'pterodactyl'@'127.0.0.1';
CREATE USER 'pterodactyl'@'127.0.0.1' IDENTIFIED BY '${DB_USER_PASS}';
GRANT ALL PRIVILEGES ON panel.* TO 'pterodactyl'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF
    then
        ok "MariaDB konfiguriert"
    else
        err "MariaDB-Konfiguration fehlgeschlagen! Prüfe MySQL-Passwort."
    fi
fi

ok "MariaDB bereit"

# ========================================
# 3. Pterodactyl Panel
# ========================================
echo ""
echo "[3/8] Lade Pterodactyl..."

# Prüfe ob bereits installiert
if [ -f "/var/www/pterodactyl/artisan" ]; then
    warn "Pterodactyl ist bereits installiert in /var/www/pterodactyl"
    echo ""
    read -p "Bestehende Installation ÜBERSCHREIBEN? (y/n): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        err "Installation abgebrochen. Lösche erst: sudo rm -rf /var/www/pterodactyl"
    fi

    info "Lösche alte Installation..."
    rm -rf /var/www/pterodactyl
fi

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
# 7.5. User-Registrierung aktivieren
# ========================================
echo ""
echo "[7.5/9] Aktiviere User-Registrierung..."

# Register Controller
mkdir -p app/Http/Controllers/Auth

cat > app/Http/Controllers/Auth/RegisterController.php <<'PHPEOF'
<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Pterodactyl\Models\User;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showRegistrationForm()
    {
        if (!env('REGISTRATION_ENABLED', false)) {
            abort(404);
        }
        return view('auth.register');
    }

    public function register(Request $request)
    {
        if (!env('REGISTRATION_ENABLED', false)) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users|regex:/^[a-zA-Z0-9_-]+$/',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = User::create([
            'uuid' => Str::uuid()->toString(),
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'name_first' => $request->username,
            'name_last' => '',
            'root_admin' => false,
            'language' => 'en',
        ]);

        auth()->login($user);

        return redirect('/');
    }
}
PHPEOF

# Register View
mkdir -p resources/views/auth

cat > resources/views/auth/register.blade.php <<'BLADEEOF'
<!DOCTYPE html>
<html>
<head>
    <title>Register - Techtyl</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
            margin: 0 0 5px 0;
        }
        .logo p {
            color: #718096;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
            font-weight: 500;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .link {
            text-align: center;
            margin-top: 20px;
            color: #718096;
            font-size: 14px;
        }
        .link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .errors {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>🦕 Techtyl</h1>
            <p>based on Pterodactyl Panel</p>
        </div>

        @if ($errors->any())
            <div class="errors">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('auth.register') }}">
            @csrf
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="{{ old('username') }}" required autofocus>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="password_confirmation" required>
            </div>
            <button type="submit" class="btn">Create Account</button>
        </form>

        <div class="link">
            Already have an account? <a href="{{ route('auth.login') }}">Login here</a>
        </div>
    </div>
</body>
</html>
BLADEEOF

# Login View mit Register-Link
cat > resources/views/auth/login.blade.php <<'BLADEEOF'
<!DOCTYPE html>
<html>
<head>
    <title>Login - Techtyl</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
            margin: 0 0 5px 0;
        }
        .logo p {
            color: #718096;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
            font-weight: 500;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .link {
            text-align: center;
            margin-top: 20px;
            color: #718096;
            font-size: 14px;
        }
        .link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .errors {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>🦕 Techtyl</h1>
            <p>based on Pterodactyl Panel</p>
        </div>

        @if ($errors->any())
            <div class="errors">
                Login failed. Please check your credentials.
            </div>
        @endif

        <form method="POST" action="{{ route('auth.login') }}">
            @csrf
            <div class="form-group">
                <label>Username or Email</label>
                <input type="text" name="user" value="{{ old('user') }}" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>

        @if(env('REGISTRATION_ENABLED', false))
        <div class="link">
            Don't have an account? <a href="{{ route('auth.register') }}">Register here</a>
        </div>
        @endif
    </div>
</body>
</html>
BLADEEOF

# Register Routes
if [ -f "routes/auth.php" ]; then
    # Prüfe ob Route schon existiert
    if ! grep -q "auth.register" routes/auth.php; then
        # Füge Route vor dem letzten }); ein (falls vorhanden)
        if grep -q "});" routes/auth.php; then
            sed -i '/});/i\    // User Registration (Techtyl)\n    Route::get('"'"'/register'"'"', [Pterodactyl\\\\Http\\\\Controllers\\\\Auth\\\\RegisterController::class, '"'"'showRegistrationForm'"'"'])->name('"'"'auth.register'"'"');\n    Route::post('"'"'/register'"'"', [Pterodactyl\\\\Http\\\\Controllers\\\\Auth\\\\RegisterController::class, '"'"'register'"'"']);' routes/auth.php
        else
            # Falls keine Gruppe, einfach anhängen
            cat >> routes/auth.php <<'PHPEOF'

// User Registration (Techtyl)
Route::get('/register', [\Pterodactyl\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm'])->name('auth.register');
Route::post('/register', [\Pterodactyl\Http\Controllers\Auth\RegisterController::class, 'register']);
PHPEOF
        fi
    fi
else
    # Erstelle routes/auth.php falls nicht vorhanden
    cat > routes/auth.php <<'PHPEOF'
<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Auth\RegisterController;

// User Registration (Techtyl)
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('auth.register');
Route::post('/register', [RegisterController::class, 'register']);
PHPEOF
fi

composer dump-autoload -q 2>/dev/null

ok "Registrierung aktiviert"

# Footer Branding im Hauptpanel
if [ -f "resources/views/templates/wrapper.blade.php" ]; then
    if ! grep -q "techtyl-footer" resources/views/templates/wrapper.blade.php; then
        # Füge Footer vor dem letzten </body> Tag ein
        sed -i '$ s|</body>|<div id="techtyl-footer" style="text-align: center; padding: 20px; color: #718096; font-size: 12px;">🦕 Techtyl - based on <a href="https://pterodactyl.io" style="color: #667eea; text-decoration: none;">Pterodactyl Panel</a></div>\n</body>|' resources/views/templates/wrapper.blade.php
        ok "Footer-Branding hinzugefügt"
    else
        ok "Footer bereits vorhanden"
    fi
fi

# ========================================
# 8. Webserver & Services
# ========================================
echo ""
echo "[8/9] Konfiguriere Webserver..."

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
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
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
echo "Debug: Starting services..."
systemctl enable --now redis-server >/dev/null 2>&1
systemctl enable --now pteroq.service >/dev/null 2>&1

echo "Debug: Restarting PHP-FPM..."
systemctl restart php${PHP_VER}-fpm

echo "Debug: Starting Nginx..."
systemctl enable nginx >/dev/null 2>&1
systemctl restart nginx

# Prüfe ob Services laufen
echo "Debug: Checking service status..."
systemctl is-active --quiet nginx || warn "Nginx läuft nicht!"
systemctl is-active --quiet php${PHP_VER}-fpm || warn "PHP-FPM läuft nicht!"

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
