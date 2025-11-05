#!/bin/bash

# Techtyl Installation Script
# One-Click Installation für Techtyl Server Panel

set -e

echo "========================================="
echo "  Techtyl by Pterodactyl - Installation"
echo "========================================="
echo ""

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Funktionen
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}!${NC} $1"
}

# Prüfe Root-Rechte
if [[ $EUID -ne 0 ]]; then
   print_error "Dieses Script muss als root ausgeführt werden"
   exit 1
fi

print_success "Root-Rechte vorhanden"

# Erkenne OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$NAME
    VER=$VERSION_ID
else
    print_error "Betriebssystem konnte nicht erkannt werden"
    exit 1
fi

print_success "Betriebssystem erkannt: $OS $VER"

# Installiere Abhängigkeiten
echo ""
echo "Installiere System-Abhängigkeiten..."

if [[ "$OS" == *"Ubuntu"* ]] || [[ "$OS" == *"Debian"* ]]; then
    apt update
    apt install -y software-properties-common curl apt-transport-https ca-certificates gnupg

    # PHP Repository
    add-apt-repository -y ppa:ondrej/php
    apt update

    # Installiere Pakete
    apt install -y php8.2 php8.2-{common,cli,gd,mysql,mbstring,bcmath,xml,fpm,curl,zip} \
                   mariadb-server nginx redis-server certbot python3-certbot-nginx \
                   git composer nodejs npm

elif [[ "$OS" == *"CentOS"* ]] || [[ "$OS" == *"Rocky"* ]]; then
    dnf install -y epel-release
    dnf install -y php82 php82-{common,cli,gd,mysqlnd,mbstring,bcmath,xml,fpm,curl,zip} \
                   mariadb-server nginx redis git composer nodejs npm
fi

print_success "System-Abhängigkeiten installiert"

# MySQL Datenbank einrichten
echo ""
echo "Richte Datenbank ein..."
systemctl start mariadb
systemctl enable mariadb

DB_PASSWORD=$(openssl rand -base64 32)
mysql -e "CREATE DATABASE IF NOT EXISTS techtyl;"
mysql -e "CREATE USER IF NOT EXISTS 'techtyl'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
mysql -e "GRANT ALL PRIVILEGES ON techtyl.* TO 'techtyl'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

print_success "Datenbank eingerichtet"

# Lade Techtyl herunter
echo ""
echo "Lade Techtyl herunter..."
cd /var/www
git clone https://github.com/yourusername/techtyl.git
cd techtyl

print_success "Techtyl heruntergeladen"

# Backend einrichten
echo ""
echo "Richte Backend ein..."
cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env

# Konfiguriere .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=techtyl/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=techtyl/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD}/" .env

php artisan key:generate --force
php artisan migrate --force --seed
php artisan storage:link

print_success "Backend eingerichtet"

# Frontend bauen
echo ""
echo "Baue Frontend..."
cd ../frontend
npm install
npm run build

print_success "Frontend gebaut"

# Nginx konfigurieren
echo ""
echo "Konfiguriere Webserver..."
cat > /etc/nginx/sites-available/techtyl <<EOF
server {
    listen 80;
    server_name _;
    root /var/www/techtyl/frontend/dist;

    index index.html;

    # XSS Protection Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Content-Security-Policy "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';" always;

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    location /api {
        proxy_pass http://localhost:8000;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF

ln -sf /etc/nginx/sites-available/techtyl /etc/nginx/sites-enabled/techtyl
nginx -t && systemctl reload nginx

print_success "Webserver konfiguriert"

# Starte Services
systemctl enable nginx redis-server
systemctl start nginx redis-server

# Admin-User erstellen
echo ""
read -p "Admin E-Mail: " ADMIN_EMAIL
read -sp "Admin Passwort: " ADMIN_PASSWORD
echo ""

cd /var/www/techtyl/backend
php artisan techtyl:create-admin "$ADMIN_EMAIL" "$ADMIN_PASSWORD"

# Zusammenfassung
echo ""
echo "========================================="
echo "  Installation abgeschlossen!"
echo "========================================="
echo ""
echo "Panel URL: http://$(hostname -I | awk '{print $1}')"
echo "Admin E-Mail: $ADMIN_EMAIL"
echo ""
echo "Datenbank-Zugangsdaten:"
echo "  Datenbank: techtyl"
echo "  Benutzer: techtyl"
echo "  Passwort: $DB_PASSWORD"
echo ""
print_warning "Bitte notiere diese Zugangsdaten!"
echo ""
print_success "Techtyl ist jetzt bereit!"
