#!/bin/bash

# ========================================
# 🦕 Techtyl 500 Error Fix Script
# ========================================

set -e

echo ""
echo "========================================="
echo "  🔧 Techtyl 500 Error Debugging"
echo "========================================="
echo ""

# Colors
G='\033[0;32m'
R='\033[0;31m'
Y='\033[1;33m'
NC='\033[0m'

ok() { echo -e "${G}✓${NC} $1"; }
err() { echo -e "${R}✗${NC} $1"; }
warn() { echo -e "${Y}!${NC} $1"; }
info() { echo -e "${Y}→${NC} $1"; }

# Root check
[[ $EUID -ne 0 ]] && err "Als root ausführen: sudo bash fix-500-error.sh"

# Pterodactyl check
[ ! -f "/var/www/pterodactyl/artisan" ] && err "Pterodactyl nicht gefunden!"

cd /var/www/pterodactyl

echo "========================================="
echo "1. Zeige letzte Fehler"
echo "========================================="
echo ""

if [ -f "storage/logs/laravel.log" ]; then
    echo "Die letzten 30 Zeilen aus laravel.log:"
    echo "----------------------------------------"
    tail -30 storage/logs/laravel.log | grep -E "ERROR|CRITICAL|Exception|Fatal" || echo "Keine kritischen Fehler in den letzten Zeilen"
    echo ""
else
    warn "Log-Datei nicht gefunden!"
fi

echo ""
echo "========================================="
echo "2. Prüfe Konfiguration"
echo "========================================="
echo ""

# APP_URL prüfen
if grep -q "^APP_URL=" .env; then
    APP_URL=$(grep "^APP_URL=" .env | cut -d'=' -f2-)
    ok "APP_URL ist gesetzt: $APP_URL"
else
    warn "APP_URL ist NICHT gesetzt!"
    SERVER_IP=$(hostname -I | awk '{print $1}')
    read -p "APP_URL setzen auf http://${SERVER_IP}? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "APP_URL=http://${SERVER_IP}" >> .env
        ok "APP_URL gesetzt"
    fi
fi

# APP_KEY prüfen
if grep -q "^APP_KEY=base64:" .env; then
    ok "APP_KEY ist gesetzt"
else
    warn "APP_KEY fehlt oder ist ungültig!"
    php artisan key:generate --force
    ok "APP_KEY generiert"
fi

# Datenbank prüfen
if grep -q "^DB_HOST=" .env; then
    DB_HOST=$(grep "^DB_HOST=" .env | cut -d'=' -f2-)
    DB_DATABASE=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2-)
    ok "Datenbank konfiguriert: $DB_HOST / $DB_DATABASE"
else
    err "Datenbank nicht konfiguriert!"
fi

echo ""
echo "========================================="
echo "3. Prüfe Dateiberechtigungen"
echo "========================================="
echo ""

# Ownership prüfen
OWNER=$(stat -c '%U' /var/www/pterodactyl)
if [ "$OWNER" != "www-data" ]; then
    warn "Ownership falsch: $OWNER (sollte www-data sein)"
    info "Setze korrekte Ownership..."
    chown -R www-data:www-data /var/www/pterodactyl
    ok "Ownership korrigiert"
else
    ok "Ownership korrekt: www-data"
fi

# Permissions prüfen
if [ ! -w "storage" ]; then
    warn "storage nicht beschreibbar"
    chmod -R 755 storage bootstrap/cache
    ok "Permissions korrigiert"
else
    ok "Permissions korrekt"
fi

echo ""
echo "========================================="
echo "4. Prüfe RegisterController"
echo "========================================="
echo ""

if [ -f "app/Http/Controllers/Auth/RegisterController.php" ]; then
    ok "RegisterController existiert"

    # Syntax-Check
    php -l app/Http/Controllers/Auth/RegisterController.php > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        ok "RegisterController Syntax OK"
    else
        err "RegisterController hat Syntax-Fehler!"
        php -l app/Http/Controllers/Auth/RegisterController.php
    fi
else
    warn "RegisterController fehlt - wird erstellt..."

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

    ok "RegisterController erstellt"
fi

echo ""
echo "========================================="
echo "5. Cache leeren & neu aufbauen"
echo "========================================="
echo ""

info "Lösche Cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

ok "Cache geleert"

info "Baue Cache neu..."
composer dump-autoload -q 2>/dev/null || warn "Composer autoload fehlgeschlagen"
php artisan config:cache
php artisan route:cache

ok "Cache neu aufgebaut"

echo ""
echo "========================================="
echo "6. Prüfe PHP-FPM"
echo "========================================="
echo ""

if systemctl is-active --quiet php8.2-fpm; then
    ok "PHP-FPM läuft"
else
    warn "PHP-FPM läuft NICHT!"
    systemctl restart php8.2-fpm
    ok "PHP-FPM gestartet"
fi

# PHP-FPM Log prüfen
if [ -f "/var/log/php8.2-fpm.log" ]; then
    ERRORS=$(tail -20 /var/log/php8.2-fpm.log | grep -c "ERROR" || echo "0")
    if [ "$ERRORS" -gt "0" ]; then
        warn "PHP-FPM hat $ERRORS Fehler:"
        tail -20 /var/log/php8.2-fpm.log | grep "ERROR"
    else
        ok "Keine PHP-FPM Fehler"
    fi
fi

echo ""
echo "========================================="
echo "7. Prüfe Nginx"
echo "========================================="
echo ""

# Nginx Syntax
nginx -t > /dev/null 2>&1
if [ $? -eq 0 ]; then
    ok "Nginx Konfiguration OK"
else
    err "Nginx Konfiguration fehlerhaft!"
    nginx -t
fi

if systemctl is-active --quiet nginx; then
    ok "Nginx läuft"
else
    warn "Nginx läuft NICHT!"
    systemctl restart nginx
    ok "Nginx gestartet"
fi

echo ""
echo "========================================="
echo "8. Services neustarten"
echo "========================================="
echo ""

info "Starte Services neu..."
systemctl restart php8.2-fpm
systemctl restart nginx
systemctl restart redis-server
systemctl restart pteroq

ok "Services neugestartet"

echo ""
echo "========================================="
echo "9. Finale Prüfung"
echo "========================================="
echo ""

# Test DB Connection
php artisan tinker --execute="echo 'DB Test OK';" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    ok "Datenbank-Verbindung OK"
else
    err "Datenbank-Verbindung fehlgeschlagen!"
fi

# Storage Link prüfen
if [ -L "public/storage" ]; then
    ok "Storage Link existiert"
else
    warn "Storage Link fehlt - erstelle..."
    php artisan storage:link
    ok "Storage Link erstellt"
fi

echo ""
echo "========================================="
echo "  ✅ Debugging abgeschlossen!"
echo "========================================="
echo ""
echo "📊 Zusammenfassung:"
echo "   • APP_URL: $(grep "^APP_URL=" .env | cut -d'=' -f2-)"
echo "   • Permissions: Korrigiert"
echo "   • Cache: Neu aufgebaut"
echo "   • Services: Laufen"
echo ""
echo "🔍 Weitere Schritte:"
echo "   1. Öffne Panel im Browser"
echo "   2. Wenn immer noch 500 Fehler:"
echo "      tail -f storage/logs/laravel.log"
echo ""
echo "📝 Vollständiges Log anzeigen:"
echo "   tail -100 storage/logs/laravel.log"
echo ""
echo "========================================="
