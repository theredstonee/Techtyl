#!/bin/bash

# ========================================
# 🦕 Techtyl Quick Fix
# Behebt: Doppelte Footer, Register 500, Rechte
# ========================================

set -e

echo ""
echo "========================================="
echo "  🔧 Techtyl Quick Fix"
echo "========================================="
echo ""

# Colors
G='\033[0;32m'
R='\033[0;31m'
Y='\033[1;33m'
NC='\033[0m'

ok() { echo -e "${G}✓${NC} $1"; }
err() { echo -e "${R}✗${NC} $1"; exit 1; }
warn() { echo -e "${Y}!${NC} $1"; }

[[ $EUID -ne 0 ]] && err "Als root ausführen: sudo bash quick-fix.sh"

# Detect PHP version
if command -v php8.3 &> /dev/null; then
    PHP_VER="8.3"
elif command -v php${PHP_VER} &> /dev/null; then
    PHP_VER="8.2"
else
    PHP_VER="8.2"
fi

echo "Using PHP $PHP_VER"

[ ! -f "/var/www/pterodactyl/artisan" ] && err "Pterodactyl nicht gefunden!"

cd /var/www/pterodactyl

echo "[1/5] Entferne doppelte Footer..."

if [ -f "resources/views/templates/wrapper.blade.php" ]; then
    # Zähle Footer
    FOOTER_COUNT=$(grep -c "techtyl-footer\|based on Pterodactyl" resources/views/templates/wrapper.blade.php || echo "0")

    if [ "$FOOTER_COUNT" -gt "1" ]; then
        warn "Gefunden: $FOOTER_COUNT Footer - entferne alle..."

        # Entferne alle Footer
        sed -i '/techtyl-footer/d' resources/views/templates/wrapper.blade.php
        sed -i '/based on Pterodactyl/d' resources/views/templates/wrapper.blade.php

        # Füge EINEN Footer ein
        sed -i '$ s|</body>|<div id="techtyl-footer" style="text-align: center; padding: 20px; color: #718096; font-size: 12px;">🦕 Techtyl - based on <a href="https://pterodactyl.io" style="color: #667eea; text-decoration: none;">Pterodactyl Panel</a></div>\n</body>|' resources/views/templates/wrapper.blade.php

        ok "Footer korrigiert (nur noch 1x)"
    else
        ok "Footer OK"
    fi
fi

echo ""
echo "[2/5] Prüfe RegisterController..."

if [ ! -f "app/Http/Controllers/Auth/RegisterController.php" ]; then
    warn "RegisterController fehlt - erstelle..."

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
else
    ok "RegisterController OK"
fi

echo ""
echo "[3/5] Prüfe Routes..."

if [ -f "routes/auth.php" ]; then
    if ! grep -q "auth.register" routes/auth.php; then
        warn "Register Route fehlt - füge hinzu..."

        cat >> routes/auth.php <<'PHPEOF'

// User Registration (Techtyl)
Route::get('/register', [\Pterodactyl\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm'])->name('auth.register');
Route::post('/register', [\Pterodactyl\Http\Controllers\Auth\RegisterController::class, 'register']);
PHPEOF

        ok "Register Route hinzugefügt"
    else
        ok "Register Route OK"
    fi
else
    warn "routes/auth.php fehlt - erstelle..."

    cat > routes/auth.php <<'PHPEOF'
<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Auth\RegisterController;

Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('auth.register');
Route::post('/register', [RegisterController::class, 'register']);
PHPEOF

    ok "routes/auth.php erstellt"
fi

echo ""
echo "[4/5] Setze Berechtigungen..."

chown -R www-data:www-data /var/www/pterodactyl
chmod -R 755 storage bootstrap/cache
ok "Berechtigungen gesetzt"

echo ""
echo "[5/5] Rebuilde Cache..."

composer dump-autoload -q 2>/dev/null || warn "Composer autoload skip"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
ok "Cache neu gebaut"

echo ""
echo "Starte Services neu..."
systemctl restart php${PHP_VER}-fpm nginx

echo ""
echo "========================================="
echo "  ✅ Quick Fix abgeschlossen!"
echo "========================================="
echo ""
echo "Teste jetzt:"
echo "  • Login: $(grep "^APP_URL=" .env | cut -d'=' -f2-)/auth/login"
echo "  • Register: $(grep "^APP_URL=" .env | cut -d'=' -f2-)/auth/register"
echo ""
echo "Falls weiterhin Fehler:"
echo "  sudo tail -50 storage/logs/laravel.log"
echo ""
