# 🚀 Techtyl by Pterodactyl

<div align="center">

![Techtyl Logo](https://via.placeholder.com/200x200?text=Techtyl)

**Das moderne Server-Management-Panel mit KI-Unterstützung**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel 11](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![React 18](https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=white)](https://react.dev/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-3-38B2AC?logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)

[Features](#-features) • [Installation](#-installation) • [Dokumentation](#-dokumentation) • [Demo](#-demo) • [Contributing](#-contributing)

</div>

---

## 📖 Über Techtyl

Techtyl ist ein modernes, benutzerfreundliches Server-Management-Panel, inspiriert von Pterodactyl. Es kombiniert die Leistungsfähigkeit von Laravel und React mit der Intelligenz von Claude AI, um die Server-Verwaltung so einfach wie nie zuvor zu machen.

### 🎯 Hauptziele

- **🤖 KI-Unterstützung**: Intelligente Empfehlungen für Server-Konfiguration
- **🚀 Einfache Installation**: One-Click-Setup statt komplexer Konfiguration
- **👥 Self-Service**: Benutzer können eigenständig Server erstellen
- **🔒 Sicherheit**: Umfassender XSS-Schutz auf allen Ebenen
- **💎 Moderne Tech-Stack**: Laravel 11, React 18, PHP 8.2+

## ✨ Features

### 🤖 Claude AI-Integration

- **Automatische Konfiguration**: Die KI schlägt optimale CPU, RAM und Disk-Werte vor
- **Namen-Generierung**: Kreative Server-Namen auf Knopfdruck
- **Interaktive Hilfe**: Stelle Fragen zur Server-Konfiguration
- **Troubleshooting**: Intelligente Problemlösung

### 🎮 Server-Management

- **Multi-Game-Support**: Minecraft, Rust, ARK, CS, Valheim, und mehr
- **Live-Ressourcen**: Echtzeit CPU/RAM/Disk-Anzeige mit Prozentbalken
- **Volle Kontrolle**: Start, Stop, Restart, Delete
- **Status-Tracking**: Visueller Status für jeden Server

### 👤 Benutzer-Verwaltung

- **Self-Service Registrierung**: Benutzer registrieren sich selbst
- **E-Mail-Verifizierung**: Optional aktivierbar
- **Server-Limits**: Flexible Limits pro Benutzer
- **Sichere Auth**: Laravel Sanctum Token-System

### 🎨 Modernes UI

- **Responsive Design**: Funktioniert auf Desktop, Tablet und Mobile
- **Dark/Light Mode**: (geplant)
- **Intuitive Bedienung**: Keine Einarbeitung notwendig
- **Echtzeit-Updates**: Live-Daten ohne Reload

### 🔒 Sicherheit

- ✅ **XSS-Protection** (Frontend + Backend)
- ✅ **CSRF-Protection** (Laravel Sanctum)
- ✅ **SQL Injection-Schutz** (Eloquent ORM)
- ✅ **Bcrypt Password-Hashing**
- ✅ **Security Headers** (CSP, X-Frame-Options, etc.)
- ✅ **Input-Validierung** (Client + Server)

## 🚀 Installation

### Schnellstart (empfohlen)

```bash
# One-Click-Installation (als root)
curl -sSL https://raw.githubusercontent.com/yourusername/techtyl/main/install.sh | bash
```

Das war's! 🎉 Das Script installiert automatisch:
- PHP 8.2 + Extensions
- MySQL/MariaDB
- Nginx
- Redis
- Node.js
- Alle Dependencies

### Docker (Alternative)

```bash
# Repository klonen
git clone https://github.com/yourusername/techtyl.git
cd techtyl

# .env-Datei konfigurieren
cp backend/.env.example backend/.env
nano backend/.env  # Claude API Key eintragen!

# Docker Container starten
docker-compose up -d
```

### Manuelle Installation

Siehe [SETUP.md](SETUP.md) für detaillierte Anweisungen.

## 🔑 Claude API Key erhalten

1. Gehe zu https://console.anthropic.com/
2. Erstelle einen Account (falls noch nicht vorhanden)
3. Navigiere zu "API Keys"
4. Erstelle einen neuen API Key
5. Kopiere den Key in deine `.env`:
   ```env
   CLAUDE_API_KEY=sk-ant-your-key-here
   ```

## 📸 Screenshots

<details>
<summary>🖼️ Screenshots anzeigen</summary>

### Dashboard
![Dashboard](https://via.placeholder.com/800x400?text=Dashboard+Screenshot)

### Server erstellen mit AI
![Create Server](https://via.placeholder.com/800x400?text=Create+Server+Screenshot)

### AI-Assistent
![AI Assistant](https://via.placeholder.com/800x400?text=AI+Assistant+Screenshot)

</details>

## 🎯 Demo

🔗 **Live-Demo**: [https://demo.techtyl.io](https://demo.techtyl.io) *(coming soon)*

**Test-Zugangsdaten**:
- E-Mail: `demo@techtyl.io`
- Passwort: `Demo123!`

## 📚 Dokumentation

| Dokument | Beschreibung |
|----------|--------------|
| [SETUP.md](SETUP.md) | Detaillierte Setup-Anleitung |
| [FEATURES.md](FEATURES.md) | Vollständige Feature-Liste |
| [PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md) | Technische Projekt-Übersicht |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Contribution-Guidelines |

## 🛠️ Tech-Stack

### Backend
- **Framework**: Laravel 11
- **Sprache**: PHP 8.2+
- **Datenbank**: MySQL 8.0+ / PostgreSQL 13+
- **Cache**: Redis 7+
- **AI**: Claude API (Anthropic)

### Frontend
- **Framework**: React 18
- **Sprache**: TypeScript 5
- **Styling**: Tailwind CSS 3
- **State**: Zustand
- **Build**: Vite 5
- **Icons**: Lucide React

### DevOps
- **Webserver**: Nginx / Apache
- **Container**: Docker + Docker Compose
- **CI/CD**: GitHub Actions *(geplant)*

## 📊 Projektstruktur

```
Techtyl/
├── backend/          # Laravel Backend (API)
├── frontend/         # React Frontend (UI)
├── scripts/          # Hilfsskripte
├── docs/            # Dokumentation
├── install.sh       # One-Click-Installer
└── docker-compose.yml
```

Siehe [PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md) für Details.

## 🤝 Contributing

Wir freuen uns über Beiträge! 🎉

1. Fork das Projekt
2. Erstelle einen Feature-Branch (`git checkout -b feature/AmazingFeature`)
3. Commit deine Änderungen (`git commit -m 'feat: Add AmazingFeature'`)
4. Push zum Branch (`git push origin feature/AmazingFeature`)
5. Öffne einen Pull Request

Siehe [CONTRIBUTING.md](CONTRIBUTING.md) für Details.

## 🗺️ Roadmap

### ✅ Phase 1 (aktuell)
- [x] Basis-Setup (Laravel + React)
- [x] Authentifizierung (Login/Register)
- [x] Server-CRUD
- [x] Claude AI-Integration
- [x] Dashboard
- [x] XSS-Protection

### 🚧 Phase 2 (nächste)
- [ ] Docker-Container-Integration
- [ ] File-Manager
- [ ] SSH-Terminal im Browser
- [ ] Backup & Restore
- [ ] Automatische Updates

### 🔮 Phase 3 (zukünftig)
- [ ] Multi-Node-Support
- [ ] Billing-System
- [ ] Plugin-System
- [ ] Mobile App
- [ ] Two-Factor Auth (2FA)

## 📈 Status

- **Version**: 1.0.0-alpha
- **Status**: In Entwicklung
- **Lizenz**: MIT
- **Maintainer**: [@yourusername](https://github.com/yourusername)

## ⚠️ Wichtige Hinweise

- **Entwicklungsstatus**: Techtyl ist aktuell in Alpha-Phase
- **Produktions-Einsatz**: Nicht empfohlen für kritische Systeme
- **Backups**: Immer Backups erstellen vor Updates
- **Sicherheit**: Security-Updates regelmäßig einspielen

## 🐛 Bug Reports & Feature Requests

- **Bugs**: [GitHub Issues](https://github.com/yourusername/techtyl/issues)
- **Features**: [GitHub Discussions](https://github.com/yourusername/techtyl/discussions)
- **Security**: security@techtyl.io

## 📞 Support & Community

- **Discord**: [Join our Discord](https://discord.gg/techtyl) *(coming soon)*
- **Dokumentation**: [docs.techtyl.io](https://docs.techtyl.io) *(coming soon)*
- **E-Mail**: support@techtyl.io

## 👏 Credits

- **Inspiration**: [Pterodactyl Panel](https://pterodactyl.io)
- **AI**: [Claude by Anthropic](https://www.anthropic.com/)
- **Framework**: [Laravel](https://laravel.com/) & [React](https://react.dev/)

## ⭐ Star History

Wenn dir Techtyl gefällt, gib uns einen ⭐ auf GitHub!

[![Stargazers over time](https://starchart.cc/yourusername/techtyl.svg)](https://starchart.cc/yourusername/techtyl)

## 📄 Lizenz

Techtyl ist Open Source und unter der [MIT Lizenz](LICENSE) verfügbar.

```
MIT License - Copyright (c) 2024 Techtyl Contributors
```

---

<div align="center">

**Erstellt mit ❤️ und 🤖 von der Techtyl Community**

[Website](https://techtyl.io) • [Docs](https://docs.techtyl.io) • [Demo](https://demo.techtyl.io)

</div>
