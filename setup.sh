#!/bin/bash

echo "========================================"
echo "  Techtyl - Einmalige Installation"
echo "========================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "[1/5] Prüfe Voraussetzungen..."
echo ""

# Check PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}[ERROR]${NC} PHP nicht gefunden!"
    echo "Bitte installiere PHP 8.2+"
    exit 1
else
    echo -e "${GREEN}[OK]${NC} PHP gefunden: $(php --version | head -n 1)"
fi

# Check Composer
if ! command -v composer &> /dev/null; then
    echo -e "${RED}[ERROR]${NC} Composer nicht gefunden!"
    echo "Bitte installiere Composer von: https://getcomposer.org/"
    exit 1
else
    echo -e "${GREEN}[OK]${NC} Composer gefunden"
fi

# Check Node.js
if ! command -v node &> /dev/null; then
    echo -e "${RED}[ERROR]${NC} Node.js nicht gefunden!"
    echo "Bitte installiere Node.js von: https://nodejs.org/"
    exit 1
else
    echo -e "${GREEN}[OK]${NC} Node.js gefunden: $(node --version)"
fi

# Check npm
if ! command -v npm &> /dev/null; then
    echo -e "${RED}[ERROR]${NC} npm nicht gefunden!"
    exit 1
else
    echo -e "${GREEN}[OK]${NC} npm gefunden"
fi

echo ""
echo "[2/5] Backend einrichten..."
cd backend

if [ ! -f ".env" ]; then
    echo "Erstelle .env Datei..."
    cp .env.example .env
    echo ""
    echo -e "${YELLOW}WICHTIG:${NC} Bitte bearbeite jetzt die .env Datei:"
    echo "  1. Setze DB_PASSWORD auf dein MySQL-Passwort"
    echo "  2. Setze CLAUDE_API_KEY (von https://console.anthropic.com/)"
    echo ""
    read -p "Drücke Enter wenn du fertig bist..."
    ${EDITOR:-nano} .env
fi

echo "Installiere Backend-Dependencies..."
composer install

echo "Generiere App-Key..."
php artisan key:generate

echo ""
read -p "Soll die Datenbank jetzt erstellt werden? (j/n): " DB_SETUP
if [[ "$DB_SETUP" == "j" || "$DB_SETUP" == "J" ]]; then
    echo ""
    echo "Erstelle Datenbank..."
    php artisan migrate
    echo -e "${GREEN}[OK]${NC} Datenbank erstellt"
else
    echo ""
    echo -e "${YELLOW}[INFO]${NC} Bitte erstelle die Datenbank manuell:"
    echo "  mysql -u root -p"
    echo "  CREATE DATABASE techtyl;"
    echo "  EXIT;"
    echo "  php artisan migrate"
fi

cd ..

echo ""
echo "[3/5] Frontend einrichten..."
cd frontend

echo "Installiere Frontend-Dependencies..."
npm install

cd ..

echo ""
echo "[4/5] Admin-Account erstellen..."
read -p "Admin-Account erstellen? (j/n): " CREATE_ADMIN
if [[ "$CREATE_ADMIN" == "j" || "$CREATE_ADMIN" == "J" ]]; then
    read -p "Admin E-Mail: " ADMIN_EMAIL
    read -sp "Admin Passwort: " ADMIN_PASS
    echo ""

    cd backend
    php artisan tinker --execute="\$u = new App\Models\User(); \$u->name = 'Admin'; \$u->email = '$ADMIN_EMAIL'; \$u->password = Hash::make('$ADMIN_PASS'); \$u->is_admin = true; \$u->server_limit = 999; \$u->email_verified_at = now(); \$u->save(); echo 'Admin erstellt';"
    cd ..
    echo -e "${GREEN}[OK]${NC} Admin-Account erstellt"
fi

echo ""
echo "[5/5] Installation abgeschlossen!"
echo ""
echo "========================================"
echo "  Techtyl ist bereit!"
echo "========================================"
echo ""
echo "Starten mit: ./start.sh"
echo "Oder manuell:"
echo "  Terminal 1: cd backend && php artisan serve"
echo "  Terminal 2: cd frontend && npm run dev"
echo ""
echo "Dann öffne: http://localhost:3000"
echo ""

# Make start.sh executable
chmod +x start.sh
