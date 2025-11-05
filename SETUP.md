# Techtyl - Setup-Anleitung

## 🚀 Schnellstart (Linux/Ubuntu)

```bash
# Als Root ausführen
curl -sSL https://raw.githubusercontent.com/yourusername/techtyl/main/install.sh | bash
```

## 📋 Voraussetzungen

### System-Anforderungen
- **OS**: Ubuntu 20.04+, Debian 11+, CentOS 8+, Rocky Linux 8+
- **RAM**: Mindestens 2GB
- **Disk**: Mindestens 20GB frei
- **Root-Zugriff** erforderlich

### Software-Abhängigkeiten
- PHP 8.2+
- MySQL 8.0+ oder PostgreSQL 13+
- Nginx oder Apache
- Redis
- Node.js 18+
- Composer
- Git

## 🔧 Manuelle Installation

### 1. Backend (Laravel) Setup

```bash
cd backend

# Abhängigkeiten installieren
composer install

# .env konfigurieren
cp .env.example .env
nano .env

# Datenbank einrichten
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

# Server starten (Entwicklung)
php artisan serve
```

### 2. Frontend (React) Setup

```bash
cd frontend

# Abhängigkeiten installieren
npm install

# Entwicklungsserver starten
npm run dev

# Für Produktion bauen
npm run build
```

## ⚙️ Konfiguration

### Backend (.env)

```env
APP_NAME=Techtyl
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=techtyl
DB_USERNAME=techtyl
DB_PASSWORD=your_secure_password

# Claude AI (erforderlich für AI-Features)
CLAUDE_API_KEY=your_claude_api_key
CLAUDE_MODEL=claude-3-5-sonnet-20241022
CLAUDE_MAX_TOKENS=4096

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Techtyl Settings
TECHTYL_REGISTRATION_ENABLED=true
TECHTYL_EMAIL_VERIFICATION=true
TECHTYL_DEFAULT_SERVER_LIMIT=3
```

### Claude API Key erhalten

1. Gehe zu: https://console.anthropic.com/
2. Erstelle einen Account
3. Navigiere zu "API Keys"
4. Erstelle einen neuen API Key
5. Kopiere den Key in deine `.env` Datei

## 🗄️ Datenbank einrichten

### MySQL

```bash
# MySQL/MariaDB installieren
sudo apt install mariadb-server

# Datenbank erstellen
sudo mysql
```

```sql
CREATE DATABASE techtyl;
CREATE USER 'techtyl'@'localhost' IDENTIFIED BY 'dein_passwort';
GRANT ALL PRIVILEGES ON techtyl.* TO 'techtyl'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Migrationen ausführen

```bash
cd backend
php artisan migrate --seed
```

## 🌐 Webserver-Konfiguration

### Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/techtyl/frontend/dist;

    index index.html;

    # XSS Protection Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Content-Security-Policy "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';" always;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### SSL mit Let's Encrypt

```bash
sudo certbot --nginx -d your-domain.com
```

## 👤 Admin-Account erstellen

```bash
cd backend
php artisan techtyl:create-admin admin@example.com "SecurePassword123"
```

Oder manuell:

```bash
php artisan tinker
```

```php
$user = new App\Models\User();
$user->name = 'Admin';
$user->email = 'admin@example.com';
$user->password = Hash::make('SecurePassword123');
$user->is_admin = true;
$user->server_limit = 999;
$user->email_verified_at = now();
$user->save();
```

## 🔒 Sicherheit

### Wichtige Sicherheitsmaßnahmen

1. **XSS-Schutz**: Bereits implementiert im Backend (Middleware) und Frontend (DOMPurify)
2. **CSRF-Schutz**: Laravel Sanctum aktiviert
3. **SQL Injection**: Laravel Eloquent ORM verhindert dies automatisch
4. **Sichere Passwörter**: Bcrypt-Hashing
5. **Rate Limiting**: Implementiert für API-Endpunkte

### Firewall einrichten

```bash
# UFW (Ubuntu)
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp
sudo ufw enable
```

## 📊 Produktions-Deployment

### Optimierungen

```bash
# Backend
cd backend
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Frontend
cd frontend
npm run build
```

### Systemd Service (Backend)

Erstelle `/etc/systemd/system/techtyl.service`:

```ini
[Unit]
Description=Techtyl Laravel Backend
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/techtyl/backend
ExecStart=/usr/bin/php artisan serve --host=127.0.0.1 --port=8000
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable techtyl
sudo systemctl start techtyl
```

## 🧪 Tests

```bash
# Backend Tests
cd backend
php artisan test

# Frontend Tests (wenn konfiguriert)
cd frontend
npm run test
```

## 📝 Logs

```bash
# Laravel Logs
tail -f backend/storage/logs/laravel.log

# Nginx Logs
tail -f /var/log/nginx/error.log
tail -f /var/log/nginx/access.log
```

## 🆘 Troubleshooting

### Problem: "SQLSTATE[HY000] [2002] Connection refused"
**Lösung**: MySQL/MariaDB läuft nicht
```bash
sudo systemctl start mariadb
```

### Problem: "Permission denied" Fehler
**Lösung**: Berechtigungen setzen
```bash
sudo chown -R www-data:www-data /var/www/techtyl
sudo chmod -R 755 /var/www/techtyl
sudo chmod -R 775 /var/www/techtyl/backend/storage
sudo chmod -R 775 /var/www/techtyl/backend/bootstrap/cache
```

### Problem: AI-Features funktionieren nicht
**Lösung**: Claude API Key überprüfen
```bash
# In backend/.env
CLAUDE_API_KEY=sk-ant-... # Muss gesetzt sein
```

## 📚 Weitere Ressourcen

- [Laravel Dokumentation](https://laravel.com/docs)
- [React Dokumentation](https://react.dev/)
- [Claude API Dokumentation](https://docs.anthropic.com/)
- [Pterodactyl Dokumentation](https://pterodactyl.io/project/introduction.html)

## 🤝 Support

Bei Problemen:
1. Prüfe die Logs
2. Suche in den Issues auf GitHub
3. Erstelle ein neues Issue mit detaillierten Informationen

## 📄 Lizenz

MIT License - Siehe LICENSE Datei
