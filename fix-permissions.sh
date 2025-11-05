#!/bin/bash

# ========================================
# 🔧 Techtyl Permissions Fix
# ========================================

set -e

echo ""
echo "========================================="
echo "  🔧 Fixe Berechtigungen"
echo "========================================="
echo ""

# Root check
[[ $EUID -ne 0 ]] && { echo "Als root ausführen: sudo bash fix-permissions.sh"; exit 1; }

# Detect PHP version
if command -v php8.3 &> /dev/null; then
    PHP_VER="8.3"
elif command -v php${PHP_VER} &> /dev/null; then
    PHP_VER="8.2"
else
    PHP_VER="8.2"
fi

echo "Using PHP $PHP_VER"

# Pterodactyl check
[ ! -f "/var/www/pterodactyl/artisan" ] && { echo "Pterodactyl nicht gefunden!"; exit 1; }

cd /var/www/pterodactyl

echo "[1/4] Setze Ownership..."
chown -R www-data:www-data /var/www/pterodactyl
echo "✓ Ownership: www-data:www-data"

echo ""
echo "[2/4] Setze Directory Permissions..."
find /var/www/pterodactyl -type d -exec chmod 755 {} \;
echo "✓ Directories: 755"

echo ""
echo "[3/4] Setze File Permissions..."
find /var/www/pterodactyl -type f -exec chmod 644 {} \;
echo "✓ Files: 644"

echo ""
echo "[4/4] Spezielle Permissions..."
chmod -R 755 storage bootstrap/cache
chmod +x artisan
echo "✓ storage/: 755"
echo "✓ bootstrap/cache/: 755"
echo "✓ artisan: executable"

echo ""
echo "Lösche Cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo ""
echo "Erstelle Storage Link..."
php artisan storage:link

echo ""
echo "Starte Services neu..."
systemctl restart php${PHP_VER}-fpm nginx

echo ""
echo "========================================="
echo "  ✅ Berechtigungen korrigiert!"
echo "========================================="
echo ""
echo "Teste jetzt das Panel im Browser!"
echo ""
