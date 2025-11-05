# 🦕 Techtyl - Pterodactyl Addon Installation

## 📋 Schnellübersicht

**Techtyl** ist ein AI-Addon für Pterodactyl Panel, das:
- ✅ **KEINE** Datenbank-Änderungen macht
- ✅ **KEIN** eigenes Panel ist
- ✅ **ERWEITERT** dein bestehendes Pterodactyl
- ✅ **EINFACH** zu installieren ist

---

## 🚀 Installation (5 Minuten)

### Voraussetzungen
- Pterodactyl Panel v1.10.0+ installiert
- Zugriff auf Server per SSH (als root/sudo)
- Azure OpenAI Account

### Schritt 1: Installation ausführen

```bash
# Ins Pterodactyl-Verzeichnis
cd /var/www/pterodactyl

# Installer herunterladen
wget https://raw.githubusercontent.com/theredstonee/Techtyl/main/install-addon.sh

# Ausführen
sudo bash install-addon.sh
```

Das Script fragt nach:
1. **Azure OpenAI API Key**
2. **Azure OpenAI Endpoint**
3. **Deployment-Name** (z.B. gpt-4o)

### Schritt 2: Azure OpenAI Credentials erhalten

Wenn du noch keinen Azure OpenAI Account hast:

1. Gehe zu: https://portal.azure.com/
2. Erstelle "Azure OpenAI" Resource
3. Region: **West Europe** (DSGVO, niedrige Latenz)
4. Deploy Model: **gpt-4o** (empfohlen)
5. Kopiere:
   - API Key (Keys and Endpoint)
   - Endpoint URL
   - Deployment-Name

### Schritt 3: Fertig!

Öffne dein Pterodactyl Panel:
```
https://deine-panel-domain.de
```

Die AI-Features erscheinen automatisch bei der Server-Erstellung!

---

## 🎨 Was wird hinzugefügt?

### In der Server-Erstellung:
- ✨ **"AI-Empfehlungen"** Button
- 🏷️ **"Namen vorschlagen"** Button
- 💬 **AI-Chat** für Fragen

### Neue API-Endpunkte:
- `/api/techtyl/ai/suggestions` - Konfigurationsempfehlungen
- `/api/techtyl/ai/help` - Hilfe-Chat
- `/api/techtyl/ai/names` - Namen-Vorschläge
- `/api/techtyl/ai/troubleshoot` - Problemlösung

---

## 📁 Dateien die hinzugefügt werden

```
/var/www/pterodactyl/
├── app/
│   ├── Services/
│   │   └── AzureOpenAIService.php          [NEU]
│   └── Http/Controllers/Techtyl/
│       └── AIController.php                 [NEU]
├── config/
│   └── techtyl.php                          [NEU]
├── resources/scripts/components/techtyl/
│   └── AIAssistant.tsx                      [NEU]
└── routes/
    └── api.php                              [GEÄNDERT - Routes hinzugefügt]
```

**KEINE Datenbank-Tabellen werden erstellt!**

---

## ⚙️ Konfiguration

### .env Einstellungen

```bash
sudo nano /var/www/pterodactyl/.env
```

Am Ende hinzufügen/ändern:
```env
# Techtyl AI Addon
TECHTYL_AI_ENABLED=true                      # AI aktivieren/deaktivieren
TECHTYL_MAX_REQUESTS=50                       # Max. Anfragen pro User/Tag
TECHTYL_CACHE_RESPONSES=true                  # Responses cachen
TECHTYL_CACHE_DURATION=1440                   # Cache 24h

# Azure OpenAI
AZURE_OPENAI_API_KEY=dein-key
AZURE_OPENAI_ENDPOINT=https://dein-resource.openai.azure.com/
AZURE_OPENAI_DEPLOYMENT=gpt-4o
AZURE_OPENAI_API_VERSION=2024-02-15-preview
```

### config/techtyl.php

```bash
sudo nano /var/www/pterodactyl/config/techtyl.php
```

```php
return [
    'enabled' => env('TECHTYL_AI_ENABLED', true),
    'max_requests_per_user' => env('TECHTYL_MAX_REQUESTS', 50),
    'cache_responses' => env('TECHTYL_CACHE_RESPONSES', true),
    'cache_duration' => env('TECHTYL_CACHE_DURATION', 1440),
];
```

---

## 🔄 Updates

```bash
cd /var/www/pterodactyl
wget https://raw.githubusercontent.com/theredstonee/Techtyl/main/install-addon.sh
sudo bash install-addon.sh
```

Das Script erkennt automatisch ob es ein Update oder Neuinstallation ist.

---

## 🗑️ Deinstallation

```bash
cd /var/www/pterodactyl

# Dateien entfernen
sudo rm -rf app/Services/AzureOpenAIService.php
sudo rm -rf app/Http/Controllers/Techtyl
sudo rm -rf resources/scripts/components/techtyl
sudo rm config/techtyl.php

# Routes entfernen (manuell)
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

## 🐛 Troubleshooting

### AI-Features erscheinen nicht

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

# Services neu starten
sudo systemctl restart pteroq
```

### "Azure OpenAI credentials not configured"

```bash
# .env prüfen
cd /var/www/pterodactyl
cat .env | grep AZURE_OPENAI

# Sollte 4 Werte zeigen:
# AZURE_OPENAI_API_KEY=...
# AZURE_OPENAI_ENDPOINT=...
# AZURE_OPENAI_DEPLOYMENT=...
# AZURE_OPENAI_API_VERSION=...

# Falls nicht da, hinzufügen:
sudo nano .env
```

### "Class not found" Fehler

```bash
cd /var/www/pterodactyl

# Composer Autoload neu generieren
sudo composer dump-autoload

# Cache leeren
php artisan config:clear
```

### Frontend-Build schlägt fehl

```bash
cd /var/www/pterodactyl/resources/scripts

# Node modules neu installieren
rm -rf node_modules
npm install

# Neu bauen
npm run build
```

---

## 💰 Kosten

### Azure OpenAI GPT-4o

Beispiel für 50 Benutzer:
- ~10 AI-Anfragen pro User/Monat
- 500 Anfragen total
- ~$0.005 pro Anfrage
- **~$2.50/Monat**

### Kostenoptimierung

Aktiviert by default:
- ✅ Response-Caching (24h)
- ✅ Rate Limiting (50/User/Tag)
- ✅ Günstigstes Model (GPT-4o statt GPT-4)

### Rate Limits anpassen

Mehr Anfragen erlauben:
```php
// config/techtyl.php
'max_requests_per_user' => 100,
```

Oder in .env:
```env
TECHTYL_MAX_REQUESTS=100
```

---

## 📊 Logs

### Pterodactyl Logs

```bash
tail -f /var/www/pterodactyl/storage/logs/laravel.log
```

### Techtyl-spezifische Logs

```bash
# Alle AI-Anfragen
tail -f /var/www/pterodactyl/storage/logs/laravel.log | grep Techtyl

# Fehler
tail -f /var/www/pterodactyl/storage/logs/laravel.log | grep ERROR
```

---

## ✅ Checkliste

Installation:
- [ ] Pterodactyl v1.10+ läuft
- [ ] Azure OpenAI Account erstellt
- [ ] Model deployt (gpt-4o)
- [ ] install-addon.sh ausgeführt
- [ ] Credentials eingetragen
- [ ] Frontend neu gebaut
- [ ] Services neu gestartet

Nach Installation:
- [ ] Panel öffnen
- [ ] Server erstellen testen
- [ ] "AI-Empfehlungen" Button sichtbar
- [ ] AI-Anfrage funktioniert
- [ ] Logs prüfen

---

## 📞 Support

- **GitHub Issues**: https://github.com/theredstonee/Techtyl/issues
- **Discussions**: https://github.com/theredstonee/Techtyl/discussions
- **Dokumentation**: [PTERODACTYL_ADDON.md](PTERODACTYL_ADDON.md)

---

## 🎯 Zusammenfassung

**3 Befehle zur Installation:**
```bash
cd /var/www/pterodactyl
wget https://raw.githubusercontent.com/theredstonee/Techtyl/main/install-addon.sh
sudo bash install-addon.sh
```

**Fertig in 5 Minuten!** 🚀
