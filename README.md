# 🦕 Techtyl - AI Addon für Pterodactyl Panel

<div align="center">

![Pterodactyl](https://img.shields.io/badge/Pterodactyl-v1.11-0e4688?style=for-the-badge&logo=pterodactyl)
![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Azure OpenAI](https://img.shields.io/badge/Azure_OpenAI-GPT--4o-0078D4?style=for-the-badge&logo=microsoft-azure)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge)](LICENSE)

**Erweitere dein Pterodactyl Panel mit AI-gestützter Server-Verwaltung**

[Features](#-features) • [Installation](#-installation) • [Dokumentation](#-dokumentation) • [Screenshots](#-screenshots)

</div>

---

## 📖 Was ist Techtyl?

Techtyl ist ein **AI-Enhancement Addon** für Pterodactyl Panel, das dein Server-Management auf das nächste Level hebt. Statt nur ein weiteres Panel zu sein, integriert sich Techtyl nahtlos in deine bestehende Pterodactyl-Installation und fügt KI-Funktionen hinzu.

### 🎯 Hauptziele

- 🤖 **AI-Assistent** für intelligente Server-Konfiguration
- 💡 **Automatische Empfehlungen** basierend auf Spiel-Typ
- 🏷️ **Namen-Generierung** für kreative Server-Namen
- 🔧 **Troubleshooting-Hilfe** bei Problemen
- 📊 **Verbesserte UX** ohne Pterodactyl zu ersetzen

---

## ✨ Features

### 🤖 AI-gestützte Server-Erstellung

- **Intelligente Ressourcen-Empfehlungen**: AI schlägt optimale CPU, RAM und Disk basierend auf Spiel-Typ vor
- **Automatische Namen**: Generiere kreative und passende Server-Namen
- **Interaktiver Assistent**: Stelle Fragen während der Server-Erstellung
- **Kontext-bewusst**: Berücksichtigt Spieler-Anzahl, Mods, etc.

### 🔧 Troubleshooting & Support

- **Problem-Analyse**: Beschreibe Fehler, erhalte Lösungen
- **Schritt-für-Schritt Hilfe**: Detaillierte Anleitungen
- **Server-spezifisch**: Berücksichtigt deine Server-Konfiguration
- **24/7 verfügbar**: Keine Wartezeiten

### 🎮 Multi-Game Support

Optimiert für:
- Minecraft (Java & Bedrock)
- Rust
- ARK: Survival Evolved
- Counter-Strike (CS:GO, CS2)
- Valheim
- Terraria
- TeamSpeak
- Discord Bots
- Und mehr...

### 🔒 Sicherheit & Compliance

- ✅ **DSGVO-konform**: Azure EU-Server
- ✅ **Keine Datenbank-Änderungen**: Pterodactyl bleibt unberührt
- ✅ **Rate Limiting**: Schutz vor Missbrauch
- ✅ **Input Sanitization**: XSS/CSRF-geschützt
- ✅ **Pterodactyl-kompatibel**: Funktioniert mit bestehender Security

---

## 🚀 Installation

### Voraussetzungen

- ✅ Pterodactyl Panel v1.11.0+
- ✅ PHP 8.1+
- ✅ Node.js 16+
- ✅ Azure OpenAI Account

### Quick Install (5 Minuten)

```bash
cd /var/www/pterodactyl
wget https://raw.githubusercontent.com/theredstonee/Techtyl/main/install-addon.sh
sudo bash install-addon.sh
```

Das Script:
1. Installiert Techtyl-Komponenten
2. Fragt nach Azure OpenAI Credentials
3. Baut Frontend neu
4. Startet Services neu

### Azure OpenAI Setup

1. Gehe zu: https://portal.azure.com/
2. Erstelle "Azure OpenAI" Resource
3. Deploy Model: **gpt-4o** (empfohlen)
4. Kopiere API Key & Endpoint

**In `.env` eintragen:**
```env
TECHTYL_AI_ENABLED=true
AZURE_OPENAI_API_KEY=dein-key
AZURE_OPENAI_ENDPOINT=https://dein-resource.openai.azure.com/
AZURE_OPENAI_DEPLOYMENT=gpt-4o
```

📚 **Detaillierte Anleitung**: [PTERODACTYL_ADDON.md](PTERODACTYL_ADDON.md)

---

## 📸 Screenshots

<details>
<summary>🖼️ Screenshots anzeigen</summary>

### AI-Assistent bei Server-Erstellung
![Server Creation](https://via.placeholder.com/800x400?text=AI+Server+Creation)

### Intelligente Empfehlungen
![AI Suggestions](https://via.placeholder.com/800x400?text=AI+Recommendations)

### Troubleshooting-Hilfe
![Troubleshooting](https://via.placeholder.com/800x400?text=AI+Troubleshooting)

</details>

---

## 🎮 Nutzung

### Für Server-Ersteller:

1. **Neuen Server erstellen** in Pterodactyl
2. **Klicke "✨ AI-Empfehlungen"** neben Ressourcen-Feldern
3. **AI schlägt vor**: CPU, RAM, Disk basierend auf Spiel
4. **Optional**: "Namen vorschlagen" für kreativen Namen
5. **Server erstellen** mit optimalen Einstellungen

### Für Admins:

```bash
# Einstellungen anpassen
sudo nano /var/www/pterodactyl/config/techtyl.php

# Logs überwachen
tail -f /var/www/pterodactyl/storage/logs/techtyl.log

# Updates installieren
cd /var/www/pterodactyl
sudo bash install-addon.sh --update
```

---

## 💰 Kosten

### Azure OpenAI Pricing (GPT-4o)

| Nutzung | Kosten/Monat | Anfragen |
|---------|--------------|----------|
| Klein (10 User) | ~$5 | 100/Monat |
| Mittel (50 User) | ~$25 | 500/Monat |
| Groß (200 User) | ~$100 | 2000/Monat |

**Kostenoptimierung:**
- ✅ Response-Caching (24h)
- ✅ Rate Limiting pro User
- ✅ Günstigstes Model (GPT-4o)

**Startguthaben**: Neue Azure-Accounts erhalten oft $5-10 kostenlos!

---

## 🛠️ Entwicklung

### Projekt-Struktur

```
Techtyl/
├── addon/
│   ├── app/
│   │   ├── Services/
│   │   │   └── AzureOpenAIService.php
│   │   └── Http/Controllers/Techtyl/
│   │       └── AIController.php
│   ├── resources/scripts/components/techtyl/
│   │   ├── AIAssistant.tsx
│   │   └── ServerWizard.tsx
│   ├── routes/
│   │   └── techtyl.php
│   └── config/
│       └── techtyl.php
├── install-addon.sh
└── docs/
```

### Lokal testen

```bash
# Pterodactyl lokal einrichten
git clone https://github.com/pterodactyl/panel.git
cd panel

# Techtyl-Addon installieren
git clone https://github.com/theredstonee/Techtyl.git temp-addon
bash temp-addon/install-addon.sh

# Development-Server
php artisan serve
npm run dev
```

---

## 🔄 Updates

```bash
cd /var/www/pterodactyl
wget https://raw.githubusercontent.com/theredstonee/Techtyl/main/install-addon.sh
sudo bash install-addon.sh --update
```

Oder manuell:
```bash
cd /var/www/pterodactyl
git pull https://github.com/theredstonee/Techtyl.git main
composer install --no-dev
cd resources/scripts && npm run build
php artisan config:clear
```

---

## 🐛 Troubleshooting

### AI-Features erscheinen nicht

```bash
cd /var/www/pterodactyl
php artisan config:clear
php artisan view:clear
cd resources/scripts && npm run build
```

### "Credentials not configured"

```bash
# .env prüfen
cat /var/www/pterodactyl/.env | grep AZURE_OPENAI

# Sollte 4 Werte zeigen
```

### Rate Limit Error

Admin kann Limit in `config/techtyl.php` erhöhen

📚 **Mehr Hilfe**: [PTERODACTYL_ADDON.md](PTERODACTYL_ADDON.md)

---

## 📊 Kompatibilität

| Pterodactyl | Techtyl | Status |
|-------------|---------|--------|
| v1.11.x | v1.x | ✅ Voll unterstützt |
| v1.10.x | v1.x | ✅ Unterstützt |
| v1.9.x | v1.x | ⚠️ Teilweise |
| v1.8.x | v1.x | ❌ Nicht getestet |

---

## 🤝 Contributing

Wir freuen uns über Beiträge! 🎉

1. Fork das Projekt
2. Erstelle Feature-Branch (`git checkout -b feature/AmazingFeature`)
3. Commit Änderungen (`git commit -m 'Add AmazingFeature'`)
4. Push zum Branch (`git push origin feature/AmazingFeature`)
5. Öffne Pull Request

Siehe [CONTRIBUTING.md](CONTRIBUTING.md) für Details.

---

## 📄 Lizenz

MIT License - Siehe [LICENSE](LICENSE)

**Kompatibel mit Pterodactyl (MIT)**

---

## 🙏 Credits

- Basiert auf [Pterodactyl Panel](https://pterodactyl.io)
- AI powered by [Azure OpenAI](https://azure.microsoft.com/en-us/products/ai-services/openai-service)
- Community-Addon (nicht offiziell von Pterodactyl)

---

## 🌟 Zeig deine Unterstützung

Wenn dir Techtyl gefällt, gib uns einen ⭐ auf GitHub!

[![Star History](https://starchart.cc/theredstonee/Techtyl.svg)](https://starchart.cc/theredstonee/Techtyl)

---

## 📞 Support & Community

- **Issues**: [GitHub Issues](https://github.com/theredstonee/Techtyl/issues)
- **Discussions**: [GitHub Discussions](https://github.com/theredstonee/Techtyl/discussions)
- **Discord**: Coming soon

---

<div align="center">

**Erstellt mit ❤️ und 🤖 für die Pterodactyl-Community**

[Website](https://techtyl.io) • [Docs](PTERODACTYL_ADDON.md) • [Azure Setup](AZURE_SETUP.md)

</div>
