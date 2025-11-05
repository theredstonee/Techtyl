# 🦕 Techtyl - AI Addon für Pterodactyl Panel

Techtyl ist ein **AI-Enhancement Addon** für Pterodactyl Panel, das intelligente Server-Konfiguration und Hilfe bietet.

## 🎯 Was ist Techtyl?

Techtyl erweitert dein bestehendes Pterodactyl Panel mit:
- 🤖 **AI-Assistent** für Server-Erstellung (Azure OpenAI GPT-4o)
- 💡 **Intelligente Empfehlungen** für CPU, RAM, Disk
- 🏷️ **Automatische Namens-Generierung**
- 🔧 **Troubleshooting-Hilfe**
- 📊 **Verbesserte UI** mit AI-Integration

## ✨ Features

### Für Benutzer:
- AI schlägt optimale Server-Konfigurationen vor
- Automatische Namen-Vorschläge basierend auf Spiel-Typ
- Interaktiver Chat-Assistent bei Server-Erstellung
- Hilfe bei Problemen und Fehlern

### Für Admins:
- Einfache Installation als Pterodactyl-Erweiterung
- Keine Datenbank-Änderungen nötig
- Azure OpenAI Integration
- Vollständig DSGVO-konform (EU-Server)

## 📋 Voraussetzungen

- **Pterodactyl Panel** v1.11.0+ installiert
- **PHP** 8.1+
- **Node.js** 16+
- **Azure OpenAI** Account (für AI-Features)

## 🚀 Installation

### Methode 1: Automatische Installation (Empfohlen)

```bash
cd /var/www/pterodactyl
wget https://raw.githubusercontent.com/theredstonee/Techtyl/main/install-addon.sh
sudo bash install-addon.sh
```

### Methode 2: Manuelle Installation

```bash
# 1. Ins Pterodactyl-Verzeichnis
cd /var/www/pterodactyl

# 2. Techtyl-Addon klonen
git clone https://github.com/theredstonee/Techtyl.git temp-techtyl

# 3. Backend-Erweiterungen kopieren
sudo cp -r temp-techtyl/addon/app/Services app/
sudo cp -r temp-techtyl/addon/app/Http/Controllers/Techtyl app/Http/Controllers/

# 4. Routes hinzufügen
sudo cat temp-techtyl/addon/routes/techtyl.php >> routes/api.php

# 5. Config hinzufügen
sudo cp temp-techtyl/addon/config/techtyl.php config/

# 6. Frontend-Komponenten kopieren
sudo cp -r temp-techtyl/addon/resources/scripts/components/techtyl resources/scripts/components/

# 7. Dependencies installieren
sudo composer require guzzlehttp/guzzle
cd resources/scripts && npm install axios

# 8. Frontend neu bauen
npm run build

# 9. Aufräumen
cd /var/www/pterodactyl
sudo rm -rf temp-techtyl

# 10. Cache leeren
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 3. Azure OpenAI konfigurieren

```bash
sudo nano /var/www/pterodactyl/.env
```

**Am Ende hinzufügen:**
```env
# Techtyl AI Addon
TECHTYL_AI_ENABLED=true
AZURE_OPENAI_API_KEY=dein-api-key
AZURE_OPENAI_ENDPOINT=https://dein-resource.openai.azure.com/
AZURE_OPENAI_DEPLOYMENT=gpt-4o
AZURE_OPENAI_API_VERSION=2024-02-15-preview
```

### 4. Panel neu starten

```bash
sudo systemctl restart pteroq
sudo systemctl restart pteroworker
php artisan queue:restart
```

## 🎨 UI Integration

Nach der Installation erscheint im Pterodactyl Panel:

1. **Server-Erstellung**: AI-Assistent-Button neben Ressourcen-Eingaben
2. **Server-Dashboard**: "AI-Hilfe" Tab für Troubleshooting
3. **Admin-Bereich**: Techtyl-Einstellungen unter "Advanced"

## 📚 Nutzung

### Für Benutzer:

#### Server erstellen mit AI:
1. Gehe zu "Create Server"
2. Wähle Spiel-Typ (Egg)
3. Klicke "✨ AI-Empfehlungen"
4. AI schlägt optimale CPU/RAM/Disk vor
5. Optional: "Namen vorschlagen" für kreativen Server-Namen

#### Troubleshooting:
1. Öffne deinen Server
2. Gehe zum "AI-Hilfe" Tab
3. Beschreibe dein Problem
4. Erhalte Schritt-für-Schritt Lösungen

### Für Admins:

#### Einstellungen anpassen:
```bash
sudo nano /var/www/pterodactyl/config/techtyl.php
```

```php
return [
    'ai_enabled' => env('TECHTYL_AI_ENABLED', true),
    'show_suggestions' => true,
    'max_requests_per_user' => 50, // Pro Tag
    'cache_responses' => true,
];
```

#### Logs überwachen:
```bash
tail -f /var/www/pterodactyl/storage/logs/techtyl.log
```

## 🔧 Konfiguration

### Azure OpenAI Setup

1. **Azure Portal**: https://portal.azure.com/
2. Erstelle "Azure OpenAI" Resource
3. Deploy Model: `gpt-4o` (empfohlen)
4. Kopiere Credentials in `.env`

### Rate Limiting

Standard: 50 AI-Anfragen pro Benutzer pro Tag

Ändern in `config/techtyl.php`:
```php
'max_requests_per_user' => 100,
```

### Cache

AI-Antworten werden 24h gecached um Kosten zu sparen.

Deaktivieren:
```php
'cache_responses' => false,
```

## 💰 Kosten

Mit Azure OpenAI GPT-4o:
- ~$0.005 pro AI-Anfrage
- 100 Benutzer mit 10 Anfragen/Monat = ~$50/Monat
- Erste $5-10 oft kostenlos (Startguthaben)

**Kostenoptimierung:**
- ✅ Response-Caching aktiviert
- ✅ Rate Limiting pro Benutzer
- ✅ Günstigstes Model (GPT-4o statt GPT-4)

## 🔄 Updates

```bash
cd /var/www/pterodactyl
sudo git pull https://github.com/theredstonee/Techtyl.git main

# Backend updaten
sudo composer install --no-dev --optimize-autoloader
php artisan config:clear

# Frontend neu bauen
cd resources/scripts
npm install
npm run build
cd ../..

# Services neu starten
sudo systemctl restart pteroq
php artisan queue:restart
```

## 🗑️ Deinstallation

```bash
cd /var/www/pterodactyl

# Dateien entfernen
sudo rm -rf app/Services/AzureOpenAIService.php
sudo rm -rf app/Http/Controllers/Techtyl
sudo rm -rf resources/scripts/components/techtyl
sudo rm config/techtyl.php

# Aus .env entfernen
sudo nano .env
# TECHTYL_* und AZURE_OPENAI_* Zeilen löschen

# Routes bereinigen (manuell)
sudo nano routes/api.php
# Techtyl-Routes entfernen

# Cache leeren
php artisan config:clear
php artisan route:clear

# Frontend neu bauen
cd resources/scripts
npm run build
cd ../..

# Fertig
sudo systemctl restart pteroq
```

## 🛡️ Sicherheit

- ✅ API-Keys sicher in `.env` gespeichert
- ✅ Rate Limiting aktiviert
- ✅ Input-Validierung und Sanitization
- ✅ DSGVO-konform (Azure EU-Server)
- ✅ Keine Datenbank-Änderungen
- ✅ Kompatibel mit Pterodactyl-Security

## 🐛 Troubleshooting

### AI-Features erscheinen nicht

```bash
# Cache leeren
cd /var/www/pterodactyl
php artisan config:clear
php artisan view:clear

# Frontend neu bauen
cd resources/scripts
npm run build
```

### "Azure OpenAI credentials not configured"

```bash
# .env prüfen
cd /var/www/pterodactyl
cat .env | grep AZURE_OPENAI

# Sollte zeigen:
# AZURE_OPENAI_API_KEY=...
# AZURE_OPENAI_ENDPOINT=...
# AZURE_OPENAI_DEPLOYMENT=...
```

### Rate Limit überschritten

Admin kann Limit erhöhen:
```php
// config/techtyl.php
'max_requests_per_user' => 100,
```

## 📊 Kompatibilität

| Pterodactyl Version | Techtyl Support |
|---------------------|-----------------|
| v1.11.x | ✅ Voll unterstützt |
| v1.10.x | ✅ Unterstützt |
| v1.9.x | ⚠️ Teilweise |
| v1.8.x | ❌ Nicht unterstützt |

## 🤝 Support

- **Issues**: https://github.com/theredstonee/Techtyl/issues
- **Discussions**: https://github.com/theredstonee/Techtyl/discussions
- **Discord**: (coming soon)

## 📄 Lizenz

MIT License - Kompatibel mit Pterodactyl

## 🙏 Credits

- Basiert auf [Pterodactyl Panel](https://pterodactyl.io)
- AI powered by [Azure OpenAI](https://azure.microsoft.com/en-us/products/ai-services/openai-service)

---

**Hinweis**: Dies ist ein Community-Addon und nicht offiziell von Pterodactyl unterstützt.
