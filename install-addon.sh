#!/bin/bash

# ========================================
# Techtyl AI Addon - Pterodactyl Installer
# ========================================

set -e

echo "========================================="
echo "  Techtyl AI Addon für Pterodactyl"
echo "========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}!${NC} $1"
}

# Prüfe ob Pterodactyl installiert ist
if [ ! -f "/var/www/pterodactyl/artisan" ]; then
    print_error "Pterodactyl Panel nicht gefunden!"
    echo "Erwarteter Pfad: /var/www/pterodactyl"
    echo ""
    echo "Installation abgebrochen."
    exit 1
fi

print_success "Pterodactyl Panel gefunden"

# Prüfe Pterodactyl Version
cd /var/www/pterodactyl
VERSION=$(php artisan --version | grep -oP '(?<=Pterodactyl Panel )[0-9.]+')
echo "Pterodactyl Version: $VERSION"

# Wechsel ins Pterodactyl-Verzeichnis
cd /var/www/pterodactyl

echo ""
echo "Installiere Techtyl AI Addon..."
echo ""

# ========================================
# 1. Backend-Komponenten installieren
# ========================================
echo "[1/7] Installiere Backend-Komponenten..."

# Temporäres Verzeichnis erstellen
mkdir -p /tmp/techtyl-addon
cd /tmp/techtyl-addon

# Addon von GitHub laden
if command -v git &> /dev/null; then
    git clone https://github.com/theredstonee/Techtyl.git .
else
    wget https://github.com/theredstonee/Techtyl/archive/refs/heads/main.zip
    unzip main.zip
    mv Techtyl-main/* .
fi

# Services kopieren
sudo cp -r addon/app/Services /var/www/pterodactyl/app/
print_success "Services installiert"

# Controller kopieren
sudo mkdir -p /var/www/pterodactyl/app/Http/Controllers/Techtyl
sudo cp -r addon/app/Http/Controllers/Techtyl/* /var/www/pterodactyl/app/Http/Controllers/Techtyl/
print_success "Controller installiert"

# Config kopieren
sudo cp addon/config/techtyl.php /var/www/pterodactyl/config/
print_success "Config installiert"

# ========================================
# 2. Routes hinzufügen
# ========================================
echo ""
echo "[2/7] Füge API-Routes hinzu..."

# Prüfe ob Routes bereits existieren
if grep -q "Techtyl AI Addon Routes" /var/www/pterodactyl/routes/api.php; then
    print_warning "Routes bereits vorhanden, überspringe..."
else
    # Routes hinzufügen
    cat addon/routes/techtyl.php | sudo tee -a /var/www/pterodactyl/routes/api.php > /dev/null
    print_success "Routes hinzugefügt"
fi

# ========================================
# 3. Frontend-Komponenten installieren
# ========================================
echo ""
echo "[3/7] Installiere Frontend-Komponenten..."

sudo mkdir -p /var/www/pterodactyl/resources/scripts/components/techtyl
sudo cp -r addon/resources/scripts/components/techtyl/* /var/www/pterodactyl/resources/scripts/components/techtyl/ 2>/dev/null || print_warning "Keine Frontend-Komponenten gefunden"

# ========================================
# 4. Dependencies installieren
# ========================================
echo ""
echo "[4/7] Installiere Dependencies..."

cd /var/www/pterodactyl

# Backend Dependencies
if command -v composer &> /dev/null; then
    sudo composer require guzzlehttp/guzzle --no-interaction
    print_success "Backend-Dependencies installiert"
fi

# Frontend Dependencies
if [ -d "resources/scripts" ]; then
    cd resources/scripts
    npm install axios 2>/dev/null || print_warning "Frontend-Dependencies übersprungen"
    cd ../..
fi

# ========================================
# 5. Azure OpenAI konfigurieren
# ========================================
echo ""
echo "[5/7] Konfiguriere Azure OpenAI..."

# Prüfe ob bereits konfiguriert
if grep -q "AZURE_OPENAI_API_KEY" /var/www/pterodactyl/.env; then
    print_warning "Azure OpenAI bereits konfiguriert"
else
    echo ""
    read -p "Azure OpenAI API Key: " AZURE_KEY
    read -p "Azure OpenAI Endpoint: " AZURE_ENDPOINT
    read -p "Azure OpenAI Deployment: " AZURE_DEPLOYMENT

    # Zu .env hinzufügen
    cat >> /var/www/pterodactyl/.env <<EOF

# Techtyl AI Addon
TECHTYL_AI_ENABLED=true
AZURE_OPENAI_API_KEY=${AZURE_KEY}
AZURE_OPENAI_ENDPOINT=${AZURE_ENDPOINT}
AZURE_OPENAI_DEPLOYMENT=${AZURE_DEPLOYMENT}
AZURE_OPENAI_API_VERSION=2024-02-15-preview
EOF

    print_success "Azure OpenAI konfiguriert"
fi

# ========================================
# 6. Frontend neu bauen
# ========================================
echo ""
echo "[6/7] Baue Frontend neu..."

cd /var/www/pterodactyl

if [ -d "resources/scripts" ]; then
    cd resources/scripts
    npm run build 2>/dev/null || print_warning "Frontend-Build fehlgeschlagen"
    cd ../..
    print_success "Frontend gebaut"
fi

# ========================================
# 7. Cache leeren und Services neu starten
# ========================================
echo ""
echo "[7/7] Starte Services neu..."

# Cache leeren
sudo php artisan config:clear
sudo php artisan route:clear
sudo php artisan view:clear
print_success "Cache geleert"

# Services neu starten
if command -v systemctl &> /dev/null; then
    sudo systemctl restart pteroq 2>/dev/null || print_warning "pteroq nicht gefunden"
    sudo systemctl restart pteroworker 2>/dev/null || print_warning "pteroworker nicht gefunden"
fi

# Queue neu starten
sudo php artisan queue:restart 2>/dev/null || print_warning "Queue-Restart übersprungen"

# Aufräumen
cd /
sudo rm -rf /tmp/techtyl-addon

# ========================================
# Fertig!
# ========================================
echo ""
echo "========================================="
echo "  Installation abgeschlossen!"
echo "========================================="
echo ""
echo "✓ Techtyl AI Addon erfolgreich installiert!"
echo ""
echo "Nächste Schritte:"
echo "1. Öffne Pterodactyl Panel"
echo "2. Erstelle einen neuen Server"
echo "3. Klicke '✨ AI-Empfehlungen' für intelligente Vorschläge"
echo ""
echo "Dokumentation: https://github.com/theredstonee/Techtyl"
echo ""
print_success "Techtyl ist bereit!"
