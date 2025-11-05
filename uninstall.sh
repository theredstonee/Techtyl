#!/bin/bash

# ========================================
# Techtyl Uninstaller v1.3.0
# Complete removal of Techtyl/Pterodactyl
# ========================================

set -e

clear
echo ""
echo "========================================="
echo "  ⚠️  Techtyl Uninstaller"
echo "========================================="
echo ""
echo "This will completely remove:"
echo "  • Pterodactyl Panel"
echo "  • All databases and data"
echo "  • Nginx configuration"
echo "  • Services"
echo ""
echo "⚠️  WARNING: This CANNOT be undone!"
echo ""
read -p "Are you absolutely sure? Type 'DELETE' to confirm: " CONFIRM

if [ "$CONFIRM" != "DELETE" ]; then
    echo "Uninstall cancelled."
    exit 1
fi

echo ""
read -p "Last chance! Type 'YES' to proceed: " CONFIRM2

if [ "$CONFIRM2" != "YES" ]; then
    echo "Uninstall cancelled."
    exit 1
fi

# Colors
G='\033[0;32m'
R='\033[0;31m'
Y='\033[1;33m'
B='\033[0;34m'
NC='\033[0m'

ok() { echo -e "${G}✓${NC} $1"; }
err() { echo -e "${R}✗${NC} $1"; }
warn() { echo -e "${Y}!${NC} $1"; }
info() { echo -e "${B}➜${NC} $1"; }

# Root check
[[ $EUID -ne 0 ]] && err "Run as root: sudo bash uninstall.sh" && exit 1

echo ""
info "Starting uninstallation..."
echo ""

# ========================================
# 1. Stop Services
# ========================================
echo "[1/6] Stopping services..."

systemctl stop pteroq 2>/dev/null || true
systemctl stop nginx 2>/dev/null || true
systemctl stop php8.3-fpm 2>/dev/null || true
systemctl stop php8.2-fpm 2>/dev/null || true

ok "Services stopped"

# ========================================
# 2. Disable Services
# ========================================
echo "[2/6] Disabling services..."

systemctl disable pteroq 2>/dev/null || true
rm -f /etc/systemd/system/pteroq.service
systemctl daemon-reload

ok "Services disabled"

# ========================================
# 3. Remove Files
# ========================================
echo "[3/6] Removing Pterodactyl files..."

if [ -d "/var/www/pterodactyl" ]; then
    rm -rf /var/www/pterodactyl
    ok "Pterodactyl directory removed"
else
    warn "Pterodactyl directory not found"
fi

# ========================================
# 4. Remove Database
# ========================================
echo "[4/6] Removing database..."

if command -v mysql >/dev/null 2>&1; then
    # Try to drop database (may fail if no root password set)
    mysql -u root -e "DROP DATABASE IF EXISTS panel;" 2>/dev/null || \
        warn "Could not drop database (may require manual removal)"

    mysql -u root -e "DROP USER IF EXISTS 'pterodactyl'@'127.0.0.1';" 2>/dev/null || true

    ok "Database removed"
else
    warn "MySQL not found"
fi

# ========================================
# 5. Remove Nginx Configuration
# ========================================
echo "[5/6] Removing Nginx configuration..."

rm -f /etc/nginx/sites-enabled/pterodactyl.conf
rm -f /etc/nginx/sites-available/pterodactyl.conf

# Restore default site if exists
if [ -f "/etc/nginx/sites-available/default" ]; then
    ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
fi

# Restart Nginx
systemctl restart nginx 2>/dev/null || true

ok "Nginx configuration removed"

# ========================================
# 6. Clean Up
# ========================================
echo "[6/6] Cleaning up..."

# Remove saved credentials
rm -f /root/techtyl-info.txt

# Remove logs (optional)
read -p "Remove logs? (y/n): " -n 1 -r REMOVE_LOGS
echo ""
if [[ $REMOVE_LOGS =~ ^[Yy]$ ]]; then
    rm -rf /var/log/pterodactyl 2>/dev/null || true
    ok "Logs removed"
else
    info "Logs kept"
fi

ok "Cleanup complete"

# ========================================
# Uninstall Complete
# ========================================

clear
echo ""
echo "========================================="
echo "  ✓ Techtyl Uninstalled"
echo "========================================="
echo ""
echo "Removed:"
echo "  • Pterodactyl Panel"
echo "  • Database (panel)"
echo "  • Nginx configuration"
echo "  • pteroq service"
echo "  • Saved credentials"
echo ""
echo "Still installed (not removed):"
echo "  • PHP"
echo "  • MariaDB"
echo "  • Nginx"
echo "  • Redis"
echo "  • Composer"
echo ""
echo "To remove these packages:"
echo "  sudo apt remove --purge php* mariadb-server nginx redis-server composer"
echo "  sudo apt autoremove"
echo ""
echo "⚠️  Make sure you have backups before removing packages!"
echo ""
