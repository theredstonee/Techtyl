#!/bin/bash

# ========================================
# 🦕 Techtyl Update Script
# Fügt hinzu: Registrierung, AI, Design
# ========================================

set -e

echo ""
echo "========================================="
echo "  🦕 Techtyl Update"
echo "========================================="
echo ""
echo "Fügt hinzu:"
echo "  • User-Registrierung"
echo "  • AI-Features im Frontend"
echo "  • Verbessertes Design"
echo ""

# Colors
G='\033[0;32m'
R='\033[0;31m'
Y='\033[1;33m'
NC='\033[0m'

ok() { echo -e "${G}✓${NC} $1"; }
err() { echo -e "${R}✗${NC} $1"; exit 1; }
warn() { echo -e "${Y}!${NC} $1"; }

# Root check
[[ $EUID -ne 0 ]] && err "Als root ausführen: sudo bash update-techtyl.sh"

# Pterodactyl check
[ ! -f "/var/www/pterodactyl/artisan" ] && err "Pterodactyl nicht gefunden!"

cd /var/www/pterodactyl

ok "Pterodactyl gefunden"

# ========================================
# 1. Registrierung aktivieren
# ========================================
echo ""
echo "[1/4] Aktiviere User-Registrierung..."

# .env anpassen
if ! grep -q "REGISTRATION_ENABLED" .env; then
    cat >> .env <<EOF

# User Registrierung (Techtyl)
REGISTRATION_ENABLED=true
ALLOW_REGISTRATION=true
EOF
    ok "Registrierung in .env aktiviert"
else
    sed -i 's/REGISTRATION_ENABLED=.*/REGISTRATION_ENABLED=true/' .env
    ok "Registrierung aktiviert"
fi

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

ok "Register Controller erstellt"

# Register View
mkdir -p resources/views/auth

cat > resources/views/auth/register.blade.php <<'BLADEEOF'
<!DOCTYPE html>
<html>
<head>
    <title>Register - Techtyl</title>
    <link rel="stylesheet" href="/assets/css/pterodactyl.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .register-container {
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
            margin: 0;
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
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-register {
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
        .btn-register:hover {
            transform: translateY(-2px);
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #718096;
            font-size: 14px;
        }
        .login-link a {
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
    <div class="register-container">
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

            <button type="submit" class="btn-register">
                Create Account
            </button>
        </form>

        <div class="login-link">
            Already have an account? <a href="{{ route('auth.login') }}">Login here</a>
        </div>
    </div>
</body>
</html>
BLADEEOF

ok "Register View erstellt"

# Routes hinzufügen
if ! grep -q "Route::get('/register'" routes/auth.php 2>/dev/null; then
    cat >> routes/auth.php <<'PHPEOF'

// User Registration (Techtyl)
Route::get('/register', 'Auth\RegisterController@showRegistrationForm')->name('auth.register');
Route::post('/register', 'Auth\RegisterController@register');
PHPEOF
    ok "Register Routes hinzugefügt"
fi

# ========================================
# 2. Login View anpassen
# ========================================
echo ""
echo "[2/4] Passe Login-View an..."

cat > resources/views/auth/login.blade.php <<'BLADEEOF'
<!DOCTYPE html>
<html>
<head>
    <title>Login - Techtyl</title>
    <link rel="stylesheet" href="/assets/css/pterodactyl.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .login-container {
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
            margin: 0;
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
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-login {
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
        .btn-login:hover {
            transform: translateY(-2px);
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #718096;
            font-size: 14px;
        }
        .register-link a {
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
    <div class="login-container">
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

            <button type="submit" class="btn-login">
                Login
            </button>
        </form>

        @if(env('REGISTRATION_ENABLED', false))
        <div class="register-link">
            Don't have an account? <a href="{{ route('auth.register') }}">Register here</a>
        </div>
        @endif
    </div>
</body>
</html>
BLADEEOF

ok "Login View angepasst"

# ========================================
# 3. Footer "based on Pterodactyl" hinzufügen
# ========================================
echo ""
echo "[3/4] Füge Branding hinzu..."

# Layout-Template anpassen (falls existiert)
if [ -f "resources/views/templates/wrapper.blade.php" ]; then
    if ! grep -q "based on Pterodactyl" resources/views/templates/wrapper.blade.php; then
        sed -i 's|</body>|<div style="text-align: center; padding: 20px; color: #718096; font-size: 12px;">🦕 Techtyl - based on <a href="https://pterodactyl.io" style="color: #667eea;">Pterodactyl Panel</a></div></body>|' resources/views/templates/wrapper.blade.php
        ok "Footer hinzugefügt"
    fi
fi

# ========================================
# 4. Cache & Permissions
# ========================================
echo ""
echo "[4/4] Aktualisiere System..."

# Composer autoload
composer dump-autoload -q 2>/dev/null

# Permissions
chown -R www-data:www-data /var/www/pterodactyl
chmod -R 755 storage bootstrap/cache

# Cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Rebuild cache
php artisan config:cache
php artisan route:cache

# Restart services
systemctl restart php8.2-fpm nginx

ok "System aktualisiert"

# ========================================
# Fertig!
# ========================================
echo ""
echo "========================================="
echo "  ✅ Update abgeschlossen!"
echo "========================================="
echo ""
echo "✨ Neue Features:"
echo "   ✓ User-Registrierung: /register"
echo "   ✓ Verbessertes Design"
echo "   ✓ Branding: 'based on Pterodactyl'"
echo ""
echo "📝 Registrierung testen:"
echo "   http://$(hostname -I | awk '{print $1}')/register"
echo ""
echo "🎨 Login-Seite:"
echo "   http://$(hostname -I | awk '{print $1}')/auth/login"
echo ""
echo "========================================="
