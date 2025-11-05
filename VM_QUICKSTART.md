# 🚀 Techtyl auf Linux VM einrichten - Schnellstart

## Methode 1: Automatisches Setup (5 Minuten) ⚡

### Schritt 1: Per SSH verbinden

```bash
# Von deinem PC aus
ssh root@DEINE-VM-IP
# oder
ssh dein-username@DEINE-VM-IP
```

### Schritt 2: Auto-Install Script ausführen

```bash
# Script herunterladen
wget https://raw.githubusercontent.com/theredstonee/Techtyl/main/deploy.sh

# Ausführbar machen
chmod +x deploy.sh

# Als Root ausführen
sudo bash deploy.sh
```

**Das Script macht automatisch:**
- ✅ Dependencies installieren (PHP, MySQL, Node.js, etc.)
- ✅ Datenbank erstellen
- ✅ Code von GitHub klonen
- ✅ Backend & Frontend einrichten
- ✅ Nginx konfigurieren
- ✅ Service starten

**Das Script fragt nach:**
1. MySQL Root-Passwort
2. Neues Datenbank-Passwort für Techtyl
3. Server-IP oder Domain

### Schritt 3: Azure OpenAI Credentials eintragen

```bash
# .env bearbeiten
cd /var/www/techtyl/backend
sudo nano .env
```

**Füge hinzu (am Ende der Datei):**
```env
# Azure OpenAI Credentials
AZURE_OPENAI_API_KEY=7q9BGXhAEjuWFzNWT5d8srjYFhTOGMUXFgGAeKrr6ywLZLnLDxX8JQQJ99BIACfhMk5XJ3w3AAAAACOGPJfr
AZURE_OPENAI_ENDPOINT=https://theredstonee-projects-resource.cognitiveservices.azure.com/
AZURE_OPENAI_DEPLOYMENT=gpt-4o
AZURE_OPENAI_API_VERSION=2024-02-15-preview
```

**Speichern:** `Strg+X` → `Y` → `Enter`

### Schritt 4: Service neu starten

```bash
sudo systemctl restart techtyl-backend
```

### Schritt 5: Admin-Account erstellen

```bash
cd /var/www/techtyl/backend
php artisan tinker
```

**In Tinker eingeben:**
```php
$user = new App\Models\User();
$user->name = 'Admin';
$user->email = 'admin@techtyl.local';
$user->password = Hash::make('Admin123!');
$user->is_admin = true;
$user->server_limit = 999;
$user->email_verified_at = now();
$user->save();
echo "Admin erstellt!\n";
exit
```

### Schritt 6: Fertig! 🎉

Öffne im Browser:
```
http://DEINE-VM-IP
```

Login mit:
- E-Mail: `admin@techtyl.local`
- Passwort: `Admin123!`

---

## Methode 2: Manuelles Setup (15 Minuten) 🔧

Falls das Auto-Script nicht funktioniert:

### 1. Per SSH verbinden
```bash
ssh root@DEINE-VM-IP
```

### 2. System aktualisieren
```bash
sudo apt update && sudo apt upgrade -y
```

### 3. Dependencies installieren

```bash
# PHP 8.2 + Extensions
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath php8.2-redis

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# MySQL
sudo apt install -y mariadb-server

# Redis
sudo apt install -y redis-server

# Nginx
sudo apt install -y nginx

# Git & Tools
sudo apt install -y git unzip
```

### 4. MySQL einrichten

```bash
# MySQL starten
sudo systemctl start mariadb
sudo systemctl enable mariadb

# Datenbank erstellen
sudo mysql
```

**In MySQL:**
```sql
CREATE DATABASE techtyl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'techtyl'@'localhost' IDENTIFIED BY 'DeinSicheresPasswort123!';
GRANT ALL PRIVILEGES ON techtyl.* TO 'techtyl'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 5. Code von GitHub klonen

```bash
# Ins Webroot wechseln
cd /var/www

# Repository klonen
sudo git clone https://github.com/theredstonee/Techtyl.git techtyl

# Ins Verzeichnis
cd techtyl
```

### 6. Backend einrichten

```bash
cd /var/www/techtyl/backend

# Dependencies installieren
sudo composer install --no-dev --optimize-autoloader

# .env erstellen
sudo cp .env.example .env

# .env bearbeiten
sudo nano .env
```

**In .env ändern/hinzufügen:**
```env
# App
APP_ENV=production
APP_DEBUG=false
APP_URL=http://DEINE-VM-IP

# Datenbank
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=techtyl
DB_USERNAME=techtyl
DB_PASSWORD=DeinSicheresPasswort123!

# Azure OpenAI (am Ende hinzufügen)
AZURE_OPENAI_API_KEY=7q9BGXhAEjuWFzNWT5d8srjYFhTOGMUXFgGAeKrr6ywLZLnLDxX8JQQJ99BIACfhMk5XJ3w3AAAAACOGPJfr
AZURE_OPENAI_ENDPOINT=https://theredstonee-projects-resource.cognitiveservices.azure.com/
AZURE_OPENAI_DEPLOYMENT=gpt-4o
AZURE_OPENAI_API_VERSION=2024-02-15-preview
```

**Speichern:** `Strg+X` → `Y` → `Enter`

```bash
# Laravel Setup
sudo php artisan key:generate
sudo php artisan migrate --force
sudo php artisan storage:link

# Cache optimieren
sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache

# Rechte setzen
sudo chown -R www-data:www-data /var/www/techtyl
sudo chmod -R 755 /var/www/techtyl
sudo chmod -R 775 /var/www/techtyl/backend/storage
sudo chmod -R 775 /var/www/techtyl/backend/bootstrap/cache
```

### 7. Frontend bauen

```bash
cd /var/www/techtyl/frontend

# Dependencies installieren
sudo npm install

# Production Build
sudo npm run build
```

### 8. Nginx konfigurieren

```bash
sudo nano /etc/nginx/sites-available/techtyl
```

**Inhalt einfügen (DEINE-VM-IP ersetzen!):**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name DEINE-VM-IP;

    root /var/www/techtyl/frontend/dist;
    index index.html;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Logs
    access_log /var/log/nginx/techtyl-access.log;
    error_log /var/log/nginx/techtyl-error.log;

    # Frontend
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Backend API
    location /api {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
```

**Nginx aktivieren:**
```bash
# Symlink erstellen
sudo ln -s /etc/nginx/sites-available/techtyl /etc/nginx/sites-enabled/

# Default-Site entfernen (optional)
sudo rm /etc/nginx/sites-enabled/default

# Nginx testen
sudo nginx -t

# Nginx neu laden
sudo systemctl reload nginx
```

### 9. Backend als Service einrichten

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

StandardOutput=append:/var/log/techtyl-backend.log
StandardError=append:/var/log/techtyl-backend-error.log

[Install]
WantedBy=multi-user.target
```

**Service aktivieren:**
```bash
sudo systemctl daemon-reload
sudo systemctl enable techtyl-backend
sudo systemctl start techtyl-backend

# Status prüfen
sudo systemctl status techtyl-backend
```

### 10. Firewall (optional aber empfohlen)

```bash
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw enable
```

### 11. Admin-Account erstellen

```bash
cd /var/www/techtyl/backend
php artisan tinker
```

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

### 12. Fertig! 🎉

```
http://DEINE-VM-IP
```

---

## 🔍 Troubleshooting

### Backend läuft nicht

```bash
# Status prüfen
sudo systemctl status techtyl-backend

# Logs anzeigen
sudo journalctl -u techtyl-backend -n 50

# Manuell testen
cd /var/www/techtyl/backend
php artisan serve
```

### 502 Bad Gateway

```bash
# Backend neu starten
sudo systemctl restart techtyl-backend

# Port prüfen
sudo netstat -tulpn | grep :8000
```

### Permission-Fehler

```bash
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
# .env prüfen
cd /var/www/techtyl/backend
cat .env | grep AZURE_OPENAI

# Sollte alle 4 Werte zeigen:
# AZURE_OPENAI_API_KEY=...
# AZURE_OPENAI_ENDPOINT=...
# AZURE_OPENAI_DEPLOYMENT=...
# AZURE_OPENAI_API_VERSION=...

# Nach Änderungen:
sudo php artisan config:clear
sudo systemctl restart techtyl-backend
```

---

## 📊 Logs anzeigen

```bash
# Backend-Logs
sudo tail -f /var/log/techtyl-backend.log

# Nginx Access
sudo tail -f /var/log/nginx/techtyl-access.log

# Nginx Errors
sudo tail -f /var/log/nginx/techtyl-error.log

# Laravel Log
sudo tail -f /var/www/techtyl/backend/storage/logs/laravel.log
```

---

## 🔄 Updates deployen

```bash
cd /var/www/techtyl

# Neuen Code pullen
sudo git pull

# Backend updaten
cd backend
sudo composer install --no-dev --optimize-autoloader
sudo php artisan migrate --force
sudo php artisan config:cache
sudo php artisan route:cache

# Frontend neu bauen
cd ../frontend
sudo npm install
sudo npm run build

# Service neu starten
sudo systemctl restart techtyl-backend
sudo systemctl reload nginx
```

---

## 🔒 SSL mit Let's Encrypt (Optional)

**Nur wenn du eine Domain hast!**

```bash
# Certbot installieren
sudo apt install -y certbot python3-certbot-nginx

# SSL-Zertifikat erstellen
sudo certbot --nginx -d deine-domain.de

# Auto-Renewal testen
sudo certbot renew --dry-run
```

---

## ✅ Checkliste

- [ ] VM läuft und per SSH erreichbar
- [ ] Dependencies installiert
- [ ] MySQL Datenbank erstellt
- [ ] Code von GitHub geklont
- [ ] Backend .env konfiguriert
- [ ] Azure Credentials eingetragen
- [ ] Migrationen ausgeführt
- [ ] Frontend gebaut
- [ ] Nginx konfiguriert
- [ ] Service läuft
- [ ] Firewall aktiviert
- [ ] Im Browser getestet
- [ ] Admin-Account erstellt

---

## 🎯 Schnellreferenz

```bash
# Service-Befehle
sudo systemctl start techtyl-backend
sudo systemctl stop techtyl-backend
sudo systemctl restart techtyl-backend
sudo systemctl status techtyl-backend

# Logs
sudo tail -f /var/log/techtyl-backend.log
sudo tail -f /var/log/nginx/techtyl-error.log

# Cache leeren
cd /var/www/techtyl/backend
sudo php artisan config:clear
sudo php artisan cache:clear

# Updates
cd /var/www/techtyl && sudo git pull
```

---

Fertig! 🚀 Techtyl läuft jetzt auf deiner VM!
