#!/bin/bash

# ========================================
# Techtyl Installer v1.2
# Pterodactyl Panel + AI (Azure OpenAI)
# ========================================

set -e

clear
echo ""
echo "========================================="
echo "  Techtyl Installer"
echo "========================================="
echo ""
echo "Installiert:"
echo "  • Pterodactyl Panel"
echo "  • Azure OpenAI Integration"
echo "  • User-Registrierung"
echo "  • Server-Erstellung fuer User"
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
[[ $EUID -ne 0 ]] && err "Als root ausfuehren: sudo bash install.sh"

# OS check
[ -f /etc/os-release ] && . /etc/os-release || err "OS nicht erkannt"
[[ "$ID" != "ubuntu" && "$ID" != "debian" ]] && err "Nur Ubuntu/Debian unterstuetzt"

info "System: $ID $VERSION_ID"

echo "Script is running correctly now!"
echo "Line endings are LF (Unix format)"

# Continue with rest of installation...
