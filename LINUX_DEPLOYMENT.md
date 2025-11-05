# 🐧 Techtyl auf Linux Server deployen (via SSH + GitHub)

Komplette Anleitung für Ubuntu/Debian Server.

---

## 📋 Voraussetzungen

- Linux Server (Ubuntu 20.04+ oder Debian 11+)
- SSH-Zugang zum Server
- Root- oder Sudo-Rechte
- Domain (optional, für SSL)

---

## 🚀 Teil 1: GitHub Repository erstellen

### 1.1 Repository auf GitHub erstellen

1. Gehe zu: https://github.com/new
2. **Repository name**: `techtyl`
3. **Visibility**:
   - ⚠️ **Private** (empfohlen, wegen API Key im Code!)
   - Oder Public (dann API Key vorher entfernen!)
4. **Klicke**: "Create repository"

### 1.2 Code auf GitHub hochladen

**Auf deinem Windows-PC:**

```bash
# In das Techtyl-Verzeichnis wechseln
cd E:\Claude\Techtyl

# Git initialisieren (falls noch nicht geschehen)
git init

# .gitignore erstellen (wichtig!)
# Erstelle Datei: .gitignore
```

**Inhalt von `.gitignore`:**
```
# Node
node_modules/
npm-debug.log

# PHP/Laravel
vendor/
.env
storage/*.key
.phpunit.result.cache

# Build
frontend/dist/
backend/public/hot
backend/public/storage

# IDE
.vscode/
.idea/

# OS
.DS_Store
Thumbs.db

# Logs
*.log
```

```bash
# Alle Dateien hinzufügen
git add .

# Ersten Commit
git commit -m "Initial commit - Techtyl by Pterodactyl"

# Remote Repository hinzufügen (ersetze USERNAME)
git remote add origin https://github.com/USERNAME/techtyl.git

# Branch umbenennen zu main (falls nötig)
git branch -M main

# Hochladen
git push -u origin main
```

**Falls Git fragt nach Anmeldung:**
- Username: Dein GitHub-Username
- Password: **GitHub Personal Access Token** (nicht dein Passwort!)
  - Token erstellen: https://github.com/settings/tokens
  - Scope: `repo` ankreuzen

---

## 🖥️ Teil 2: Server vorbereiten

### 2.1 Per SSH verbinden

**Windows (PowerShell oder CMD):**
```bash
ssh root@DEINE-SERVER-IP
# oder
ssh dein-username@DEINE-SERVER-IP
```

**Passwort eingeben oder SSH-Key verwenden**

### 2.2 System aktualisieren

```bash
# System-Pakete aktualisieren
sudo apt update && sudo apt upgrade -y
```

### 2.3 Dependencies installieren

```bash
# PHP 8.2 + Extensions
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2 php8.2-{cli,fpm,mysql,xml,mbstring,curl,zip,gd,bcmath,redis}

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Node.js 18+
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# MySQL/MariaDB
sudo apt install -y mariadb-server

# Redis
sudo apt install -y redis-server

# Nginx
sudo apt install -y nginx

# Git
sudo apt install -y git

# Unzip
sudo apt install -y unzip
```

### 2.4 MySQL sichern und Datenbank erstellen

```bash
# MySQL absichern
sudo mysql_secure_installation
# Fragen mit Y beantworten, Root-Passwort setzen

# Datenbank erstellen
sudo mysql -u root -p
```

**In MySQL:**
```sql
CREATE DATABASE techtyl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'techtyl'@'localhost' IDENTIFIED BY 'DeinSicheresPasswort123!';
GRANT ALL PRIVILEGES ON techtyl.* TO 'techtyl'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2.5 Verzeichnis erstellen

```bash
# Webroot erstellen
sudo mkdir -p /var/www/techtyl
sudo chown -R $USER:$USER /var/www/techtyl
cd /var/www/techtyl
```

---

## 📦 Teil 3: Code vom GitHub klonen

### 3.1 Git konfigurieren (falls private Repository)

```bash
# GitHub-Authentifizierung (Personal Access Token)
git config --global credential.helper store
```

### 3.2 Repository klonen

```bash
# In Webroot wechseln
cd /var/www

# Repository klonen (ersetze USERNAME)
git clone https://github.com/USERNAME/techtyl.git

# Ins Verzeichnis wechseln
cd techtyl

# Rechte setzen
sudo chown -R www-data:www-data /var/www/techtyl
sudo chmod -R 755 /var/www/techtyl
```

---

## ⚙️ Teil 4: Backend einrichten

### 4.1 Backend-Dependencies installieren

```bash
cd /var/www/techtyl/backend

# Composer-Abhängigkeiten installieren
composer install --no-dev --optimize-autoloader

# .env erstellen
cp .env.example .env
```

### 4.2 .env konfigurieren

```bash
nano .env
```

**Wichtige Einstellungen in `.env`:**

```env
APP_NAME=Techtyl
APP_ENV=production
APP_DEBUG=false
APP_URL=http://DEINE-SERVER-IP

# Datenbank
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=techtyl
DB_USERNAME=techtyl
DB_PASSWORD=DeinSicheresPasswort123!

# Redis (optional, für Production empfohlen)
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Techtyl
TECHTYL_REGISTRATION_ENABLED=true
TECHTYL_EMAIL_VERIFICATION=false
TECHTYL_DEFAULT_SERVER_LIMIT=3
```

**Speichern:** `Strg+X` → `Y` → `Enter`

### 4.3 Laravel Setup

```bash
# Application Key generieren
php artisan key:generate

# Datenbank-Migrationen ausführen
php artisan migrate --force

# Storage-Link erstellen
php artisan storage:link

# Cache optimieren
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Rechte setzen
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 4.4 Azure OpenAI Deployment-Name setzen

**WICHTIG:** Prüfe den Deployment-Namen!

```bash
nano app/Services/AzureOpenAIService.php
```

**Zeile 35 anpassen:**
```php
$this->deployment = 'dein-echter-deployment-name';
```

Gehe zu https://oai.azure.com/ → Deployments → Name kopieren

---

## 🎨 Teil 5: Frontend bauen

```bash
cd /var/www/techtyl/frontend

# Dependencies installieren
npm install

# Production-Build erstellen
npm run build

# Dateien werden nach dist/ gebaut
```

---

## 🌐 Teil 6: Nginx konfigurieren

### 6.1 Nginx-Config erstellen

```bash
sudo nano /etc/nginx/sites-available/techtyl
```

**Inhalt:**

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name DEINE-SERVER-IP;  # Oder deine-domain.de

    root /var/www/techtyl/frontend/dist;
    index index.html;

    # Security Headers (XSS-Schutz)
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self';" always;

    # Logging
    access_log /var/log/nginx/techtyl-access.log;
    error_log /var/log/nginx/techtyl-error.log;

    # Frontend (React)
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Backend API (Laravel)
    location /api {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**Speichern:** `Strg+X` → `Y` → `Enter`

### 6.2 Nginx aktivieren

```bash
# Symlink erstellen
sudo ln -s /etc/nginx/sites-available/techtyl /etc/nginx/sites-enabled/

# Default-Site deaktivieren (optional)
sudo rm /etc/nginx/sites-enabled/default

# Nginx-Konfiguration testen
sudo nginx -t

# Nginx neu laden
sudo systemctl reload nginx
```

---

## 🔄 Teil 7: Laravel als Service starten

### 7.1 Systemd Service erstellen

```bash
sudo nano /etc/systemd/system/techtyl-backend.service
```

**Inhalt:**

```ini
[Unit]
Description=Techtyl Laravel Backend
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/techtyl/backend
ExecStart=/usr/bin/php artisan serve --host=127.0.0.1 --port=8000
Restart=always
RestartSec=3

# Logging
StandardOutput=append:/var/log/techtyl-backend.log
StandardError=append:/var/log/techtyl-backend-error.log

[Install]
WantedBy=multi-user.target
```

### 7.2 Service aktivieren und starten

```bash
# Service neu laden
sudo systemctl daemon-reload

# Service aktivieren (Auto-Start)
sudo systemctl enable techtyl-backend

# Service starten
sudo systemctl start techtyl-backend

# Status prüfen
sudo systemctl status techtyl-backend
```

**Sollte "active (running)" zeigen! ✅**

---

## 🔥 Teil 8: Firewall konfigurieren

```bash
# UFW installieren (falls nicht vorhanden)
sudo apt install -y ufw

# Firewall-Regeln
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS (für später)

# Firewall aktivieren
sudo ufw enable

# Status prüfen
sudo ufw status
```

---

## 🔒 Teil 9: SSL mit Let's Encrypt (Optional aber empfohlen)

**NUR wenn du eine Domain hast!**

```bash
# Certbot installieren
sudo apt install -y certbot python3-certbot-nginx

# SSL-Zertifikat erstellen (ersetze deine-domain.de)
sudo certbot --nginx -d deine-domain.de -d www.deine-domain.de

# Fragen beantworten:
# - E-Mail-Adresse
# - Terms akzeptieren (Y)
# - Redirect to HTTPS? (2 für Yes)

# Auto-Renewal aktivieren (Certbot macht das automatisch)
sudo systemctl status certbot.timer
```

**Nginx-Config wird automatisch angepasst für HTTPS!**

---

## ✅ Teil 10: Testen

### 10.1 Backend testen

```bash
curl http://localhost:8000/api/test
# Sollte eine Response geben
```

### 10.2 Im Browser öffnen

```
http://DEINE-SERVER-IP
# oder
https://deine-domain.de
```

**Du solltest die Techtyl-Startseite sehen!** 🎉

### 10.3 Admin-Account erstellen

```bash
cd /var/www/techtyl/backend

php artisan tinker
```

**In Tinker:**
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

## 🔄 Updates deployen (später)

### Auf Windows-PC:

```bash
cd E:\Claude\Techtyl

# Änderungen committen
git add .
git commit -m "Update: Beschreibung der Änderungen"
git push
```

### Auf Linux-Server:

```bash
cd /var/www/techtyl

# Neuen Code pullen
git pull

# Backend aktualisieren
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Frontend neu bauen
cd ../frontend
npm install
npm run build

# Service neu starten
sudo systemctl restart techtyl-backend
sudo systemctl reload nginx
```

---

## 📊 Logs anzeigen

```bash
# Backend-Logs
sudo tail -f /var/log/techtyl-backend.log

# Nginx Access-Log
sudo tail -f /var/log/nginx/techtyl-access.log

# Nginx Error-Log
sudo tail -f /var/log/nginx/techtyl-error.log

# Laravel-Log
tail -f /var/www/techtyl/backend/storage/logs/laravel.log

# Systemd Service-Logs
sudo journalctl -u techtyl-backend -f
```

---

## 🐛 Troubleshooting

### Backend startet nicht

```bash
# Service-Status prüfen
sudo systemctl status techtyl-backend

# Logs anzeigen
sudo journalctl -u techtyl-backend -n 50

# Manuell testen
cd /var/www/techtyl/backend
php artisan serve
```

### 502 Bad Gateway

```bash
# Backend läuft nicht
sudo systemctl restart techtyl-backend

# Port-Konflikt prüfen
sudo netstat -tulpn | grep :8000
```

### Permission-Fehler

```bash
# Rechte korrigieren
sudo chown -R www-data:www-data /var/www/techtyl
sudo chmod -R 755 /var/www/techtyl
sudo chmod -R 775 /var/www/techtyl/backend/storage
sudo chmod -R 775 /var/www/techtyl/backend/bootstrap/cache
```

### Datenbank-Verbindung fehlgeschlagen

```bash
# MySQL läuft?
sudo systemctl status mysql

# .env prüfen
cd /var/www/techtyl/backend
cat .env | grep DB_

# Verbindung testen
php artisan tinker
DB::connection()->getPdo();
```

### AI funktioniert nicht

```bash
# Deployment-Name prüfen
cd /var/www/techtyl/backend
nano app/Services/AzureOpenAIService.php

# Zeile 35: Deployment-Name korrekt?
# Zeile 30: Endpoint korrekt?

# Nach Änderung:
sudo systemctl restart techtyl-backend
```

---

## 📝 Checkliste

- [ ] GitHub Repository erstellt
- [ ] Code auf GitHub gepusht
- [ ] Server per SSH verbunden
- [ ] Dependencies installiert
- [ ] MySQL-Datenbank erstellt
- [ ] Code geklont
- [ ] Backend konfiguriert (.env)
- [ ] Deployment-Name gesetzt
- [ ] Migrationen ausgeführt
- [ ] Frontend gebaut
- [ ] Nginx konfiguriert
- [ ] Service erstellt und gestartet
- [ ] Firewall konfiguriert
- [ ] Im Browser getestet
- [ ] Admin-Account erstellt
- [ ] SSL aktiviert (optional)

---

## 🎉 Fertig!

Techtyl läuft jetzt auf deinem Server!

**Zugriff:**
- Frontend: http://deine-server-ip oder https://deine-domain.de
- Admin: Login mit erstelltem Admin-Account

**Wichtig:**
- Backup der Datenbank regelmäßig!
- Server-Updates: `sudo apt update && sudo apt upgrade`
- GitHub als Backup nutzen (git push)
