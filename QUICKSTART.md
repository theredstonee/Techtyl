# 🚀 Techtyl - Schnellstart

## Voraussetzungen installieren

### Windows

#### 1. PHP 8.2+ installieren
```powershell
# Mit Chocolatey (empfohlen)
choco install php --version=8.2

# Oder manuell von:
# https://windows.php.net/download/
```

#### 2. Composer installieren
```powershell
# Download von:
# https://getcomposer.org/Composer-Setup.exe
# Dann einfach installieren
```

#### 3. Node.js installieren
```powershell
# Download von:
# https://nodejs.org/ (LTS Version)
# Installieren mit npm included
```

#### 4. MySQL/MariaDB installieren
```powershell
# Download von:
# https://dev.mysql.com/downloads/installer/
# Oder mit Chocolatey:
choco install mysql
```

---

## 📦 Projekt einrichten

### 1. Projekt klonen/herunterladen

```bash
# Option A: Mit Git
git clone https://github.com/yourusername/techtyl.git
cd techtyl

# Option B: ZIP herunterladen und entpacken
```

### 2. Backend einrichten

```bash
# In das Backend-Verzeichnis wechseln
cd backend

# Dependencies installieren
composer install

# .env-Datei erstellen
copy .env.example .env

# .env-Datei bearbeiten (wichtig!)
notepad .env
```

**Wichtig in .env eintragen:**
```env
# Datenbank
DB_DATABASE=techtyl
DB_USERNAME=root
DB_PASSWORD=dein_mysql_passwort

# Claude AI (ohne diesen Key funktioniert die AI nicht!)
CLAUDE_API_KEY=sk-ant-dein-api-key-hier
```

**Claude API Key erhalten:**
1. Gehe zu: https://console.anthropic.com/
2. Erstelle Account oder melde dich an
3. Gehe zu "API Keys"
4. Erstelle neuen Key
5. Kopiere in .env

```bash
# App-Key generieren
php artisan key:generate

# Datenbank erstellen (in MySQL)
mysql -u root -p
CREATE DATABASE techtyl;
EXIT;

# Migrationen ausführen
php artisan migrate

# Fertig!
```

### 3. Frontend einrichten

```bash
# In das Frontend-Verzeichnis wechseln
cd ../frontend

# Dependencies installieren
npm install

# Fertig!
```

---

## 🎯 Starten

### Einfache Methode (2 Terminals)

**Terminal 1 - Backend:**
```bash
cd backend
php artisan serve
```
➡️ Backend läuft auf: http://localhost:8000

**Terminal 2 - Frontend:**
```bash
cd frontend
npm run dev
```
➡️ Frontend läuft auf: http://localhost:3000

### Mit den Start-Skripten

**Windows:**
```bash
# Alles auf einmal starten
start.bat
```

**Linux/Mac:**
```bash
# Alles auf einmal starten
./start.sh
```

---

## 🎮 Erste Schritte

1. Öffne: http://localhost:3000
2. Klicke auf "Registrieren"
3. Erstelle einen Account
4. Erstelle deinen ersten Server mit AI-Hilfe! 🤖

---

## ⚙️ Admin-Account erstellen

```bash
cd backend
php artisan tinker
```

Dann in tinker:
```php
$user = new App\Models\User();
$user->name = 'Admin';
$user->email = 'admin@techtyl.local';
$user->password = Hash::make('Admin123!');
$user->is_admin = true;
$user->server_limit = 999;
$user->email_verified_at = now();
$user->save();
exit
```

---

## 🐛 Probleme?

### "php: command not found"
➡️ PHP ist nicht installiert oder nicht im PATH

### "composer: command not found"
➡️ Composer ist nicht installiert oder nicht im PATH

### "npm: command not found"
➡️ Node.js ist nicht installiert

### "SQLSTATE[HY000] [1049] Unknown database"
➡️ Datenbank 'techtyl' existiert nicht
```bash
mysql -u root -p
CREATE DATABASE techtyl;
EXIT;
php artisan migrate
```

### AI funktioniert nicht
➡️ Prüfe CLAUDE_API_KEY in .env
➡️ Key muss mit "sk-ant-" beginnen

### Port schon in Verwendung
```bash
# Backend auf anderem Port starten
php artisan serve --port=8001

# Oder in frontend/vite.config.ts Port ändern
```

---

## 🔧 Development-Tools

### Backend-Logs anzeigen
```bash
# Windows
type backend\storage\logs\laravel.log

# Linux/Mac
tail -f backend/storage/logs/laravel.log
```

### Datenbank zurücksetzen
```bash
cd backend
php artisan migrate:fresh
```

### Cache leeren
```bash
cd backend
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

---

## 🐳 Mit Docker (Alternative)

Wenn du Docker installiert hast:

```bash
# Starten
docker-compose up -d

# Stoppen
docker-compose down

# Logs anzeigen
docker-compose logs -f
```

Frontend: http://localhost:3000
Backend: http://localhost:8000

---

## ✅ Erfolg prüfen

- [ ] Backend erreichbar: http://localhost:8000/api/test
- [ ] Frontend erreichbar: http://localhost:3000
- [ ] Registrierung funktioniert
- [ ] Login funktioniert
- [ ] Server erstellen funktioniert
- [ ] AI-Assistent antwortet

---

## 📚 Weiterführende Links

- [Vollständige Setup-Anleitung](SETUP.md)
- [Feature-Übersicht](FEATURES.md)
- [Projekt-Dokumentation](PROJECT_OVERVIEW.md)

Viel Spaß mit Techtyl! 🎉
