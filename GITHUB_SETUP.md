# 🚀 GitHub Setup für Techtyl

## ⚠️ WICHTIG: Sicherheit zuerst!

Bevor du auf GitHub pushst, **stelle sicher**:
- ✅ `.gitignore` ist korrekt
- ✅ **KEINE** API Keys im Code
- ✅ **KEINE** `.env` Dateien mit echten Credentials
- ✅ Nur `.env.example` mit Platzhaltern

---

## 📦 Repository auf GitHub pushen

### Schritt 1: Lokales Git initialisieren

```bash
cd E:\Claude\Techtyl

# Git initialisieren (falls noch nicht geschehen)
git init

# Branch zu main umbenennen
git branch -M main
```

### Schritt 2: Prüfen was committed wird

```bash
# Zeige Dateien, die committed werden
git status

# ⚠️ PRÜFE: Keine .env Dateien mit echten Credentials!
# ⚠️ PRÜFE: Keine API Keys sichtbar!
```

**Sollte etwa so aussehen:**
```
Untracked files:
  .gitignore
  README.md
  backend/
  frontend/
  install.sh
  ...
```

**NICHT sichtbar sein sollten:**
```
✗ backend/.env (echte Credentials)
✗ .env.production.example (mit echten Keys)
```

### Schritt 3: Dateien hinzufügen

```bash
# Alle Dateien hinzufügen
git add .

# Ersten Commit erstellen
git commit -m "Initial commit: Techtyl by Pterodactyl

- Modern server management panel with AI
- Azure OpenAI integration
- React + TypeScript frontend
- Laravel backend
- XSS protection
- One-click installation"
```

### Schritt 4: Zu GitHub pushen

```bash
# Remote Repository hinzufügen
git remote add origin https://github.com/theredstonee/Techtyl.git

# Pushen
git push -u origin main
```

**Falls Git nach Login fragt:**
- **Username**: theredstonee
- **Password**: Nutze **Personal Access Token** (nicht Passwort!)
  - Token erstellen: https://github.com/settings/tokens
  - Scopes: `repo` (alle ankreuzen)

---

## 🔐 Nach dem Push: API Key rotieren

**WICHTIG**: Da der API Key möglicherweise in der History ist:

### In Azure Portal:

1. Gehe zu: https://portal.azure.com/
2. Deine OpenAI Resource → "Keys and Endpoint"
3. Klicke **"Regenerate Key 1"**
4. Kopiere den **neuen Key**

### Auf deinem Server (.env aktualisieren):

```bash
# Per SSH verbinden
ssh dein-user@dein-server

# .env bearbeiten
nano /var/www/techtyl/backend/.env

# Neuen Key eintragen:
AZURE_OPENAI_API_KEY=neuer-key-hier

# Service neu starten
sudo systemctl restart techtyl-backend
```

### Lokal (.env.production.example aktualisieren):

```bash
# Auf Windows
cd E:\Claude\Techtyl
nano .env.production.example

# Neuen Key eintragen, committen
git add .env.production.example
git commit -m "Update API key reference"
git push
```

---

## 📋 GitHub Repository Settings

### Repository-Einstellungen optimieren:

1. **Gehe zu**: https://github.com/theredstonee/Techtyl/settings

2. **General**:
   - ✅ Issues aktiviert
   - ✅ Discussions aktiviert (für Community)
   - ✅ Wiki deaktiviert (nutze README)

3. **Branches** → Branch protection:
   - Branch: `main`
   - ✅ "Require pull request reviews before merging"
   - ✅ "Require status checks to pass"

4. **Security**:
   - ✅ Dependabot alerts aktivieren
   - ✅ Secret scanning aktivieren

---

## 📝 GitHub Repository aufhübschen

### 1. About-Sektion ausfüllen

Gehe zu: https://github.com/theredstonee/Techtyl

Rechts oben "⚙️" (Settings) → About:

**Description:**
```
Modern server management panel with AI assistance - powered by Azure OpenAI
```

**Website:**
```
https://techtyl.io
```

**Topics** (Tags hinzufügen):
```
server-management
pterodactyl
laravel
react
typescript
azure-openai
ai-assistant
panel
gaming-servers
```

### 2. Social Preview Image erstellen

- Größe: 1280 x 640 px
- Mit Logo und "Techtyl" Text
- Upload unter: Repository Settings → Social preview

### 3. License Badge hinzufügen

README.md hat bereits Badges! ✅

---

## 🏷️ GitHub Releases

### Ersten Release erstellen:

1. Gehe zu: https://github.com/theredstonee/Techtyl/releases
2. Klicke "Create a new release"
3. **Tag version**: `v1.0.0-alpha`
4. **Release title**: `Techtyl v1.0.0 Alpha`
5. **Description**:

```markdown
## 🎉 First Public Alpha Release

Techtyl ist ein modernes Server-Management-Panel mit AI-Unterstützung.

### ✨ Features

- 🤖 **AI-Assistant** powered by Azure OpenAI (GPT-4o)
- 🎮 **Multi-Game Support** (Minecraft, Rust, ARK, etc.)
- 🔒 **Security First** (XSS-Protection, CSRF, etc.)
- 🚀 **Easy Installation** (One-Click-Script)
- 📊 **Live Monitoring** (CPU, RAM, Disk)
- 👥 **Self-Service** (User Registration)

### 📦 Installation

```bash
# One-Click Installation
curl -sSL https://raw.githubusercontent.com/theredstonee/Techtyl/main/install.sh | bash
```

### 📚 Documentation

- [Setup Guide](SETUP.md)
- [Azure OpenAI Setup](AZURE_SETUP.md)
- [Linux Deployment](LINUX_DEPLOYMENT.md)

### ⚠️ Status

**Alpha**: Not recommended for production use yet!

### 🙏 Credits

Inspired by [Pterodactyl Panel](https://pterodactyl.io)
```

6. **Publish release**

---

## 📊 GitHub Actions (CI/CD) - Optional

Erstelle `.github/workflows/tests.yml`:

```yaml
name: Tests

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: cd backend && composer install
      - name: Run tests
        run: cd backend && php artisan test

  frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      - name: Install dependencies
        run: cd frontend && npm install
      - name: Build
        run: cd frontend && npm run build
```

---

## 🌟 Community fördern

### Issue Templates erstellen

`.github/ISSUE_TEMPLATE/bug_report.md`:

```markdown
---
name: Bug Report
about: Melde einen Fehler
title: '[BUG] '
labels: bug
---

**Beschreibung**
Was ist das Problem?

**Schritte zur Reproduktion**
1. Gehe zu '...'
2. Klicke auf '...'
3. Fehler erscheint

**Erwartetes Verhalten**
Was sollte passieren?

**Screenshots**
Falls zutreffend

**System:**
- OS: [z.B. Ubuntu 22.04]
- Browser: [z.B. Chrome 120]
- Techtyl Version: [z.B. 1.0.0]
```

### Contributing Guide

Bereits vorhanden: `CONTRIBUTING.md` ✅

---

## ✅ Final Checklist

Vor dem Push:
- [ ] `.gitignore` vorhanden und korrekt
- [ ] Keine echten API Keys im Code
- [ ] `.env.example` nur mit Platzhaltern
- [ ] README.md professionell
- [ ] LICENSE vorhanden
- [ ] SECURITY.md vorhanden

Nach dem Push:
- [ ] Repository About ausgefüllt
- [ ] Topics hinzugefügt
- [ ] API Key in Azure rotiert
- [ ] Ersten Release erstellt
- [ ] Issue Templates erstellt

---

## 🎯 Fertig!

Dein Repository ist jetzt professionell aufgesetzt! 🎉

**Repository**: https://github.com/theredstonee/Techtyl
