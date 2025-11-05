# 🚀 Techtyl - Schnellstart für Linux VM

## Voraussetzungen

- ✅ **Pterodactyl Panel v1.10+** bereits installiert auf `/var/www/pterodactyl`
- ✅ **SSH-Zugriff** auf deinen Server (als root oder sudo)
- ✅ **Azure OpenAI Account** mit GPT-4o Deployment

---

## Installation in 3 Schritten

### 1️⃣ Verbinde dich per SSH

```bash
ssh root@deine-server-ip
# oder
ssh dein-user@deine-server-ip
```

### 2️⃣ Führe den Installer aus

```bash
cd /var/www/pterodactyl
wget https://raw.githubusercontent.com/theredstonee/Techtyl/main/install-addon.sh
sudo bash install-addon.sh
```

### 3️⃣ Gib deine Azure OpenAI Credentials ein

Der Installer fragt nach:
- **Azure OpenAI API Key**: `abc123xyz...`
- **Azure OpenAI Endpoint**: `https://dein-resource.openai.azure.com/`
- **Deployment Name**: `gpt-4o` (oder dein Deployment-Name)

**Fertig!** Das wars! 🎉

---

## Was passiert während der Installation?

Der Installer macht automatisch:

1. ✓ Prüft ob Pterodactyl installiert ist
2. ✓ Lädt Techtyl-Dateien von GitHub
3. ✓ Kopiert Backend-Komponenten (PHP Services & Controllers)
4. ✓ Kopiert Frontend-Komponenten (React/TypeScript)
5. ✓ Fügt API-Routes hinzu
6. ✓ Installiert Dependencies (Guzzle HTTP)
7. ✓ Konfiguriert `.env` mit deinen Credentials
8. ✓ Baut das Frontend neu
9. ✓ Leert Laravel-Cache
10. ✓ Startet Services neu

**Dauer:** ~2-5 Minuten (je nach Internetgeschwindigkeit)

---

## Azure OpenAI Credentials finden

Falls du noch keine hast:

1. Gehe zu [Azure Portal](https://portal.azure.com/)
2. Navigiere zu deiner **Azure OpenAI Resource**
3. Klicke auf **"Keys and Endpoint"**

Du findest dort:
```
API Key: abc123xyz456...
Endpoint: https://dein-resource-name.openai.azure.com/
```

4. Gehe zu **"Model deployments"**
5. Notiere den **Deployment-Namen** (z.B. `gpt-4o`)

---

## Nach der Installation testen

### ✅ Pterodactyl Panel öffnen

```
https://dein-panel-domain.de
```

### ✅ Neuen Server erstellen

1. Gehe zu **Admin Panel** → **Servers** → **Create New**
2. Wähle ein **Egg** aus (z.B. "Minecraft: Java")
3. Du siehst jetzt:
   - ✨ **"AI-Empfehlungen"** Button
   - 🏷️ **"Namen vorschlagen"** Button
   - 💬 **"AI-Chat"** Eingabefeld

### ✅ AI-Features testen

Klicke auf **"✨ AI-Empfehlungen"**:
- AI schlägt optimale CPU, RAM und Disk-Werte vor
- Basierend auf dem gewählten Spiel/Egg

Klicke auf **"🏷️ Namen vorschlagen"**:
- AI generiert 5 kreative Server-Namen

Stelle eine Frage im **Chat**:
- z.B. "Wie viel RAM brauche ich für 20 Spieler?"

---

## Troubleshooting

### Problem: "AI-Features erscheinen nicht"

```bash
cd /var/www/pterodactyl

# Cache leeren
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Frontend neu bauen
cd resources/scripts
npm run build
cd ../..

# Browser-Cache leeren (STRG + SHIFT + R)
```

### Problem: "Azure OpenAI credentials not configured"

```bash
cd /var/www/pterodactyl
nano .env

# Prüfe diese Zeilen existieren:
TECHTYL_AI_ENABLED=true
AZURE_OPENAI_API_KEY=dein-key-hier
AZURE_OPENAI_ENDPOINT=https://dein-endpoint-hier/
AZURE_OPENAI_DEPLOYMENT=gpt-4o
AZURE_OPENAI_API_VERSION=2024-02-15-preview

# Speichern: STRG + X → Y → ENTER

# Cache neu laden
php artisan config:clear
```

### Problem: "Class not found"

```bash
cd /var/www/pterodactyl
sudo composer dump-autoload
php artisan config:clear
```

### Logs prüfen

```bash
# Laravel Logs
tail -f /var/www/pterodactyl/storage/logs/laravel.log

# Nur Techtyl-Logs
tail -f /var/www/pterodactyl/storage/logs/laravel.log | grep Techtyl

# Nur Fehler
tail -f /var/www/pterodactyl/storage/logs/laravel.log | grep ERROR
```

---

## Update durchführen

Einfach den Installer nochmal ausführen:

```bash
cd /var/www/pterodactyl
wget https://raw.githubusercontent.com/theredstonee/Techtyl/main/install-addon.sh
sudo bash install-addon.sh
```

Der Installer erkennt automatisch, dass es ein Update ist.

---

## Deinstallation

```bash
cd /var/www/pterodactyl

# Dateien entfernen
sudo rm -rf app/Services/AzureOpenAIService.php
sudo rm -rf app/Http/Controllers/Techtyl
sudo rm -rf resources/scripts/components/techtyl
sudo rm config/techtyl.php

# Routes manuell entfernen
sudo nano routes/api.php
# Lösche die Zeilen mit "Techtyl AI Addon Routes"

# .env bereinigen
sudo nano .env
# Lösche TECHTYL_* und AZURE_OPENAI_* Zeilen

# Cache leeren
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Frontend neu bauen
cd resources/scripts
npm run build
cd ../..

# Services neu starten
sudo systemctl restart pteroq
php artisan queue:restart
```

---

## Konfiguration anpassen

### Rate Limits ändern

```bash
sudo nano /var/www/pterodactyl/.env
```

```env
# Von 50 auf 100 Anfragen pro User/Tag erhöhen
TECHTYL_MAX_REQUESTS=100
```

### Cache-Dauer ändern

```env
# Von 24h auf 12h reduzieren
TECHTYL_CACHE_DURATION=720
```

### AI-Features temporär deaktivieren

```env
# AI komplett ausschalten
TECHTYL_AI_ENABLED=false
```

Nach Änderungen:
```bash
php artisan config:clear
```

---

## Kostenübersicht

### Azure OpenAI GPT-4o Preise (Beispiel)

**50 Benutzer, ~10 Anfragen/User/Monat:**
- 500 Anfragen total
- ~$0.005 pro Anfrage
- **≈ $2.50/Monat**

**Kostenoptimierung (bereits aktiv):**
- ✅ Response-Caching (24h)
- ✅ Rate Limiting (50/User/Tag)
- ✅ Günstiges Model (GPT-4o)

---

## Hilfe & Support

- 📖 **Vollständige Doku**: [ADDON_INSTALL_GUIDE.md](ADDON_INSTALL_GUIDE.md)
- 🐛 **Bug melden**: [GitHub Issues](https://github.com/theredstonee/Techtyl/issues)
- 💬 **Diskussion**: [GitHub Discussions](https://github.com/theredstonee/Techtyl/discussions)
- 📚 **Pterodactyl Doku**: [Pterodactyl Docs](https://pterodactyl.io/documentation)

---

## Zusammenfassung

**Installation:**
```bash
cd /var/www/pterodactyl
wget https://raw.githubusercontent.com/theredstonee/Techtyl/main/install-addon.sh
sudo bash install-addon.sh
```

**Credentials eingeben wenn gefragt:**
- Azure OpenAI API Key
- Azure OpenAI Endpoint
- Deployment-Name

**Fertig!** 🚀

Öffne Pterodactyl Panel und erstelle einen neuen Server - die AI-Features sind jetzt verfügbar!
