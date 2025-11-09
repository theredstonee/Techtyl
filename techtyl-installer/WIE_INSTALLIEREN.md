# 🚀 TECHTYL INSTALLER - INSTALLATIONSANLEITUNG

## SCHRITT 1: Installer vervollständigen (2 Minuten)

Kopiere die fehlenden Dateien vom Pterodactyl Installer:

### Auf Windows (PowerShell als Administrator):

```powershell
cd E:\Claude\Techtyl\techtyl-installer

# Kopiere die Installationsdateien
Copy-Item -Path "..\pterodactyl-installer-master\pterodactyl-installer-master\installers" -Destination . -Recurse -Force
Copy-Item -Path "..\pterodactyl-installer-master\pterodactyl-installer-master\configs" -Destination . -Recurse -Force
Copy-Item -Path "..\pterodactyl-installer-master\pterodactyl-installer-master\scripts" -Destination . -Recurse -Force

Write-Host "Fertig!" -ForegroundColor Green
```

### Auf Linux/Mac:

```bash
cd techtyl-installer

# Kopiere die Installationsdateien
cp -r ../pterodactyl-installer-master/pterodactyl-installer-master/installers ./
cp -r ../pterodactyl-installer-master/pterodactyl-installer-master/configs ./
cp -r ../pterodactyl-installer-master/pterodactyl-installer-master/scripts ./

echo "Fertig!"
```

---

## SCHRITT 2: Installer auf deinen Server übertragen

### Option A: GitHub (empfohlen)

1. **Erstelle ein GitHub Repository:**
   - Gehe zu https://github.com/new
   - Name: `techtyl-installer`
   - Private oder Public wählen
   - Erstellen

2. **Pushe den Installer:**

```bash
cd techtyl-installer
git init
git add .
git commit -m "Initial Techtyl installer"
git branch -M main
git remote add origin https://github.com/DEIN-USERNAME/techtyl-installer.git
git push -u origin main
```

3. **Auf dem Server installieren:**

```bash
bash <(curl -s https://raw.githubusercontent.com/DEIN-USERNAME/techtyl-installer/main/install.sh)
```

### Option B: Direkt per SCP/SFTP übertragen

```bash
# Lokal (von E:\Claude\Techtyl\)
scp -r techtyl-installer root@dein-server.com:/root/

# Auf dem Server
ssh root@dein-server.com
cd /root/techtyl-installer
chmod +x install.sh
./install.sh
```

---

## SCHRITT 3: Installation auf dem Server

**Voraussetzungen:**
- Frischer Ubuntu 22.04 oder 24.04 Server
- Root-Zugriff
- Mindestens 2GB RAM

**Ausführen:**

```bash
# Als root
cd /root/techtyl-installer
chmod +x install.sh
./install.sh
```

**Im Menü wählen:**

```
[0] Install the Techtyl panel       <- Für Panel-Installation
[1] Install Techtyl Wings           <- Für Wings-Installation
[2] Install both                    <- Für beides
```

---

## SCHRITT 4: Installation durchführen

Der Installer fragt dich nach:

### Für Panel:

1. **Datenbank-Konfiguration**
   - DB Name: `panel` (Enter drücken)
   - DB User: `techtyl` (Enter drücken)
   - DB Passwort: (Enter = automatisch generiert)

2. **Timezone**
   - `Europe/Berlin` (Enter drücken)

3. **Email**
   - Deine Email für Let's Encrypt

4. **Admin-Account**
   - Email, Username, Vorname, Nachname, Passwort

5. **Domain (FQDN)**
   - z.B. `panel.deinedomain.de`

6. **Firewall**
   - `y` für automatische Konfiguration

7. **Let's Encrypt (SSL)**
   - `y` für automatisches HTTPS

**Dann installiert der Installer automatisch:**
- ✓ MariaDB
- ✓ PHP 8.3
- ✓ Nginx
- ✓ Redis
- ✓ Techtyl Panel
- ✓ SSL-Zertifikat
- ✓ Firewall

---

## FERTIG! 🎉

Nach der Installation ist das Panel erreichbar unter:

```
https://deine-domain.de
```

**Login:**
- Email: Die Admin-Email, die du angegeben hast
- Passwort: Dein Admin-Passwort

---

## Troubleshooting

### Installation schlägt fehl:

```bash
# Log ansehen
cat /var/log/techtyl-installer.log
```

### Port 80/443 bereits in Benutzung:

```bash
# Apache stoppen (falls installiert)
systemctl stop apache2
systemctl disable apache2
```

### DNS-Fehler:

- Stelle sicher, dass deine Domain auf die Server-IP zeigt
- Warte 5-10 Minuten nach DNS-Änderung
- Teste mit: `ping deine-domain.de`

---

## Wichtige Pfade

- **Panel:** `/var/www/pterodactyl`
- **Logs:** `/var/log/techtyl-installer.log`
- **Nginx Config:** `/etc/nginx/sites-available/pterodactyl.conf`
- **SSL Zertifikat:** `/etc/letsencrypt/live/deine-domain/`

---

## Nach der Installation

1. **Erste Node erstellen** (im Panel)
2. **Wings installieren** (Option [1] im Installer)
3. **Server erstellen und genießen!**

---

## Support

- **Installer-Log:** `/var/log/techtyl-installer.log`
- **Pterodactyl Docs:** https://pterodactyl.io/panel/1.0/getting_started.html
- **Nginx Log:** `/var/log/nginx/error.log`

---

**Das wars! Viel Erfolg mit Techtyl! 🚀**
