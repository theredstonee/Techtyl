# 🧪 Techtyl Testing Guide

## Quick Fix für aktuelle Probleme

**Auf deinem Server ausführen:**

```bash
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/quick-fix.sh | sudo bash
```

Das behebt:
- ✅ Mehrfache Footer-Anzeige
- ✅ Register 500 Error
- ✅ Berechtigungsprobleme
- ✅ Cache-Probleme

---

## Testing Checklist

### 1. Footer Test
**Problem:** Footer wird mehrfach angezeigt (wie im Screenshot)

**Lösung:**
```bash
# Quick Fix ausführen
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/quick-fix.sh | sudo bash
```

**Erwartung:**
- Footer erscheint nur EINMAL am Ende der Seite
- Text: "🦕 Techtyl - based on Pterodactyl Panel"

### 2. Registrierung Test

**URL:** `http://deine-ip/auth/register`

**Schritte:**
1. Öffne `/auth/register`
2. Gebe Username, Email, Password ein
3. Klicke "Create Account"

**Erwartung:**
- ✅ KEINE 500 Error
- ✅ User wird erstellt
- ✅ Auto-Login nach Registrierung
- ✅ Redirect zum Dashboard

**Falls 500 Error:**
```bash
cd /var/www/pterodactyl
sudo tail -50 storage/logs/laravel.log
```

### 3. Login Page Test

**URL:** `http://deine-ip/auth/login`

**Erwartung:**
- ✅ "Don't have an account? Register here" Link sichtbar
- ✅ Link führt zu `/auth/register`
- ✅ Modernes Purple/Blue Design

### 4. PHP Version Test

```bash
# Prüfe PHP Version
php -v

# Prüfe ob richtige Version verwendet wird
systemctl status php8.2-fpm
# oder
systemctl status php8.3-fpm
```

**Erwartung:**
- Ubuntu 22.04 → PHP 8.2
- Ubuntu 24.04 → PHP 8.3

---

## Häufige Probleme & Lösungen

### Problem: Footer wird mehrfach angezeigt

**Ursache:** Alte Installations-Runs haben Footer mehrfach eingefügt

**Lösung:**
```bash
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/quick-fix.sh | sudo bash
```

### Problem: Register gibt 500 Error

**Mögliche Ursachen:**
1. Route nicht richtig registriert
2. Autoloader nicht neu gebaut
3. Cache nicht geleert

**Lösung:**
```bash
cd /var/www/pterodactyl

# Cache leeren
sudo php artisan config:clear
sudo php artisan cache:clear
sudo php artisan route:clear
sudo php artisan view:clear

# Autoloader neu bauen
sudo composer dump-autoload

# Cache neu aufbauen
sudo php artisan config:cache
sudo php artisan route:cache

# Services neustarten
sudo systemctl restart php8.2-fpm nginx

# Logs prüfen
sudo tail -50 storage/logs/laravel.log
```

### Problem: Registrierung nicht sichtbar auf Login-Page

**Prüfe .env:**
```bash
grep REGISTRATION_ENABLED /var/www/pterodactyl/.env
```

**Sollte sein:**
```
REGISTRATION_ENABLED=true
```

**Falls nicht:**
```bash
sudo nano /var/www/pterodactyl/.env
# Füge hinzu:
REGISTRATION_ENABLED=true

# Cache neu bauen
cd /var/www/pterodactyl
sudo php artisan config:cache
```

---

## Log-Analyse

### Laravel Log prüfen

```bash
# Letzte 50 Zeilen
sudo tail -50 /var/www/pterodactyl/storage/logs/laravel.log

# Live Log
sudo tail -f /var/www/pterodactyl/storage/logs/laravel.log
```

### Nginx Error Log

```bash
sudo tail -50 /var/log/nginx/error.log
```

### PHP-FPM Log

```bash
sudo tail -50 /var/log/php8.2-fpm.log
# oder
sudo tail -50 /var/log/php8.3-fpm.log
```

---

## Services Status

```bash
# Alle Services prüfen
sudo systemctl status nginx
sudo systemctl status php8.2-fpm  # oder php8.3-fpm
sudo systemctl status redis-server
sudo systemctl status pteroq
sudo systemctl status mariadb

# Services neustarten (falls Probleme)
sudo systemctl restart nginx php8.2-fpm redis-server pteroq
```

---

## Manuelle Fixes

### Berechtigungen manuell setzen

```bash
cd /var/www/pterodactyl

sudo chown -R www-data:www-data .
sudo chmod -R 755 storage bootstrap/cache
sudo php artisan storage:link
```

### Register Controller manuell prüfen

```bash
# Prüfe ob Controller existiert
ls -la /var/www/pterodactyl/app/Http/Controllers/Auth/RegisterController.php

# Syntax-Check
sudo php -l /var/www/pterodactyl/app/Http/Controllers/Auth/RegisterController.php
```

### Routes manuell prüfen

```bash
cd /var/www/pterodactyl

# Liste alle Routes
sudo php artisan route:list | grep register

# Sollte zeigen:
# GET|HEAD  /register  .... auth.register
# POST      /register  ....
```

---

## Test nach Quick-Fix

### 1. Panel öffnen
```
http://deine-ip
```

### 2. Zur Login-Page
```
http://deine-ip/auth/login
```

**Prüfe:**
- ✅ Footer nur EINMAL am Ende
- ✅ "Register here" Link sichtbar
- ✅ Purple/Blue Gradient Design

### 3. Zur Register-Page
```
http://deine-ip/auth/register
```

**Prüfe:**
- ✅ KEINE 500 Error
- ✅ Formular wird angezeigt
- ✅ Branding "🦕 Techtyl - based on Pterodactyl Panel"

### 4. Account erstellen

**Eingabe:**
- Username: `testuser`
- Email: `test@example.com`
- Password: `testpassword123`
- Confirm Password: `testpassword123`

**Erwartung:**
- ✅ Erfolgreiches Submit
- ✅ Auto-Login
- ✅ Redirect zu Dashboard

---

## Erfolgreiches Setup

Wenn alles funktioniert, solltest du sehen:

1. **Login Page:**
   - Modernes Design (Purple Gradient)
   - "Register here" Link
   - Footer 1x am Ende

2. **Register Page:**
   - Formular funktioniert
   - Validierung funktioniert
   - Account wird erstellt

3. **After Registration:**
   - Auto-Login
   - Redirect zu Dashboard
   - User kann Panel nutzen

---

## Support

**Falls Probleme bleiben:**

1. **Logs sammeln:**
```bash
sudo tar -czf /root/techtyl-logs.tar.gz \
    /var/www/pterodactyl/storage/logs/ \
    /var/log/nginx/error.log \
    /var/log/php8.2-fpm.log

# Download logs und poste im GitHub Issue
```

2. **GitHub Issue erstellen:**
   https://github.com/theredstonee/Techtyl/issues

3. **Infos bereitstellen:**
   - OS Version: `cat /etc/os-release`
   - PHP Version: `php -v`
   - Logs: Upload techtyl-logs.tar.gz
   - Screenshot des Fehlers

---

## Nächste Features (Coming Soon)

- 🔜 User Server Creation
- 🔜 KI Frontend Integration
- 🔜 AI Chat Component
- 🔜 Resource Recommendations UI

---

**🦕 Techtyl v1.1 - Bug Fixes Release**

_based on Pterodactyl Panel_
