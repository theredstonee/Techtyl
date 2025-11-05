@echo off
echo ========================================
echo   Techtyl - Einmalige Installation
echo ========================================
echo.

echo [1/5] Pruefe Voraussetzungen...
echo.

REM Check PHP
php --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP nicht gefunden!
    echo Bitte installiere PHP 8.2+ von: https://windows.php.net/download/
    pause
    exit /b 1
) else (
    echo [OK] PHP gefunden
)

REM Check Composer
composer --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Composer nicht gefunden!
    echo Bitte installiere Composer von: https://getcomposer.org/
    pause
    exit /b 1
) else (
    echo [OK] Composer gefunden
)

REM Check Node.js
node --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Node.js nicht gefunden!
    echo Bitte installiere Node.js von: https://nodejs.org/
    pause
    exit /b 1
) else (
    echo [OK] Node.js gefunden
)

REM Check npm
npm --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] npm nicht gefunden!
    pause
    exit /b 1
) else (
    echo [OK] npm gefunden
)

echo.
echo [2/5] Backend einrichten...
cd backend

if not exist ".env" (
    echo Erstelle .env Datei...
    copy .env.example .env
    echo.
    echo WICHTIG: Bitte bearbeite jetzt die .env Datei:
    echo   1. Setze DB_PASSWORD auf dein MySQL-Passwort
    echo   2. Setze CLAUDE_API_KEY (von https://console.anthropic.com/)
    echo.
    notepad .env
    echo.
    pause
)

echo Installiere Backend-Dependencies...
call composer install

echo Generiere App-Key...
php artisan key:generate

echo.
echo Datenbank-Setup:
echo.
set /p DB_SETUP="Soll die Datenbank jetzt erstellt werden? (j/n): "
if /i "%DB_SETUP%"=="j" (
    echo.
    echo Erstelle Datenbank...
    php artisan migrate
    echo [OK] Datenbank erstellt
) else (
    echo.
    echo [INFO] Bitte erstelle die Datenbank manuell:
    echo   mysql -u root -p
    echo   CREATE DATABASE techtyl;
    echo   EXIT;
    echo   php artisan migrate
)

cd ..

echo.
echo [3/5] Frontend einrichten...
cd frontend

echo Installiere Frontend-Dependencies...
call npm install

cd ..

echo.
echo [4/5] Admin-Account erstellen...
set /p CREATE_ADMIN="Admin-Account erstellen? (j/n): "
if /i "%CREATE_ADMIN%"=="j" (
    set /p ADMIN_EMAIL="Admin E-Mail: "
    set /p ADMIN_PASS="Admin Passwort: "

    cd backend
    php artisan tinker --execute="$u = new App\Models\User(); $u->name = 'Admin'; $u->email = '%ADMIN_EMAIL%'; $u->password = Hash::make('%ADMIN_PASS%'); $u->is_admin = true; $u->server_limit = 999; $u->email_verified_at = now(); $u->save(); echo 'Admin erstellt';"
    cd ..
    echo [OK] Admin-Account erstellt
)

echo.
echo [5/5] Installation abgeschlossen!
echo.
echo ========================================
echo   Techtyl ist bereit!
echo ========================================
echo.
echo Starten mit: start.bat
echo Oder manuell:
echo   Terminal 1: cd backend ^&^& php artisan serve
echo   Terminal 2: cd frontend ^&^& npm run dev
echo.
echo Dann oeffne: http://localhost:3000
echo.
pause
