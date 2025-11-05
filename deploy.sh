#!/bin/bash

# ========================================
# Techtyl - Auto-Deployment-Script
# Für Ubuntu/Debian Server
# ========================================

set -e

echo "========================================="
echo "  Techtyl - Deployment auf Linux Server"
echo "========================================="
echo ""

# Colors
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

print_step() {
    echo ""
    echo -e "${YELLOW}==>${NC} $1"
    echo ""
}

# Prüfe Root
if [[ $EUID -ne 0 ]]; then
   print_error "Dieses Script muss als root ausgeführt werden (sudo)"
   exit 1
fi

print_success "Root-Rechte vorhanden"

# Prüfe OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$NAME
    print_success "OS erkannt: $OS"
else
    print_error "Betriebssystem konnte nicht erkannt werden"
    exit 1
fi

# ========================================
# SCHRITT 1: System aktualisieren
# ========================================
print_step "SCHRITT 1/10: System aktualisieren"

apt update
apt upgrade -y

print_success "System aktualisiert"

# ========================================
# SCHRITT 2: Dependencies installieren
# ========================================
print_step "SCHRITT 2/10: Dependencies installieren"

# PHP 8.2
if ! command -v php &> /dev/null; then
    add-apt-repository -y ppa:ondrej/php
    apt update
    apt install -y php8.2 php8.2-{cli,fpm,mysql,xml,mbstring,curl,zip,gd,bcmath,redis}
    print_success "PHP 8.2 installiert"
else
    print_success "PHP bereits installiert"
fi

# Composer
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    print_success "Composer installiert"
else
    print_success "Composer bereits installiert"
fi

# Node.js
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
    apt install -y nodejs
    print_success "Node.js installiert"
else
    print_success "Node.js bereits installiert"
fi

# MySQL
if ! command -v mysql &> /dev/null; then
    apt install -y mariadb-server
    systemctl start mariadb
    systemctl enable mariadb
    print_success "MySQL installiert"
else
    print_success "MySQL bereits installiert"
fi

# Redis
if ! command -v redis-cli &> /dev/null; then
    apt install -y redis-server
    systemctl start redis-server
    systemctl enable redis-server
    print_success "Redis installiert"
else
    print_success "Redis bereits installiert"
fi

# Nginx
if ! command -v nginx &> /dev/null; then
    apt install -y nginx
    systemctl start nginx
    systemctl enable nginx
    print_success "Nginx installiert"
else
    print_success "Nginx bereits installiert"
fi

# Git
apt install -y git unzip

# ========================================
# SCHRITT 3: Datenbank erstellen
# ========================================
print_step "SCHRITT 3/10: Datenbank konfigurieren"

read -p "Datenbank-Root-Passwort: " -s DB_ROOT_PASS
echo ""
read -p "Neues Datenbank-Passwort für Techtyl: " -s DB_PASS
echo ""

mysql -u root -p"$DB_ROOT_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS techtyl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'techtyl'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON techtyl.* TO 'techtyl'@'localhost';
FLUSH PRIVILEGES;
EOF

print_success "Datenbank erstellt"

# ========================================
# SCHRITT 4: Code klonen
# ========================================
print_step "SCHRITT 4/10: Code von GitHub klonen"

read -p "GitHub Repository URL (z.B. https://github.com/username/techtyl.git): " REPO_URL

if [ -d "/var/www/techtyl" ]; then
    print_warning "Verzeichnis existiert bereits. Überspringen..."
else
    cd /var/www
    git clone "$REPO_URL" techtyl
    print_success "Code geklont"
fi

cd /var/www/techtyl

# ========================================
# SCHRITT 5: Backend einrichten
# ========================================
print_step "SCHRITT 5/10: Backend einrichten"

cd /var/www/techtyl/backend

composer install --no-dev --optimize-autoloader

if [ ! -f .env ]; then
    cp .env.example .env
fi

# .env anpassen
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env
sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env

php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

print_success "Backend konfiguriert"

# ========================================
# SCHRITT 6: Frontend bauen
# ========================================
print_step "SCHRITT 6/10: Frontend bauen"

cd /var/www/techtyl/frontend
npm install
npm run build

print_success "Frontend gebaut"

# ========================================
# SCHRITT 7: Rechte setzen
# ========================================
print_step "SCHRITT 7/10: Dateiberechtigungen setzen"

chown -R www-data:www-data /var/www/techtyl
chmod -R 755 /var/www/techtyl
chmod -R 775 /var/www/techtyl/backend/storage
chmod -R 775 /var/www/techtyl/backend/bootstrap/cache

print_success "Rechte gesetzt"

# ========================================
# SCHRITT 8: Nginx konfigurieren
# ========================================
print_step "SCHRITT 8/10: Nginx konfigurieren"

read -p "Server-IP oder Domain: " SERVER_NAME

cat > /etc/nginx/sites-available/techtyl <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${SERVER_NAME};

    root /var/www/techtyl/frontend/dist;
    index index.html;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    access_log /var/log/nginx/techtyl-access.log;
    error_log /var/log/nginx/techtyl-error.log;

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    location /api {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF

ln -sf /etc/nginx/sites-available/techtyl /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

nginx -t && systemctl reload nginx

print_success "Nginx konfiguriert"

# ========================================
# SCHRITT 9: Systemd Service
# ========================================
print_step "SCHRITT 9/10: Backend-Service einrichten"

cat > /etc/systemd/system/techtyl-backend.service <<EOF
[Unit]
Description=Techtyl Laravel Backend
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/techtyl/backend
ExecStart=/usr/bin/php artisan serve --host=127.0.0.1 --port=8000
Restart=always
RestartSec=3

StandardOutput=append:/var/log/techtyl-backend.log
StandardError=append:/var/log/techtyl-backend-error.log

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable techtyl-backend
systemctl start techtyl-backend

print_success "Service gestartet"

# ========================================
# SCHRITT 10: Firewall
# ========================================
print_step "SCHRITT 10/10: Firewall konfigurieren"

if command -v ufw &> /dev/null; then
    ufw allow 22/tcp
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw --force enable
    print_success "Firewall konfiguriert"
else
    print_warning "UFW nicht installiert, übersprungen"
fi

# ========================================
# FERTIG!
# ========================================
echo ""
echo "========================================="
echo "  Installation abgeschlossen!"
echo "========================================="
echo ""
echo "Panel URL: http://${SERVER_NAME}"
echo ""
echo "Nächste Schritte:"
echo "1. Admin-Account erstellen:"
echo "   cd /var/www/techtyl/backend"
echo "   php artisan tinker"
echo ""
echo "2. SSL aktivieren (optional):"
echo "   sudo certbot --nginx -d ${SERVER_NAME}"
echo ""
echo "3. Azure Deployment-Name prüfen:"
echo "   nano /var/www/techtyl/backend/app/Services/AzureOpenAIService.php"
echo ""
print_success "Techtyl ist bereit!"
