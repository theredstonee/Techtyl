# 🦕 Techtyl - Änderungen

## Version: Update vom 2025-11-05

### ✨ Neue Features

#### 1. **User-Registrierung System**
- ✅ Vollständiges Registrierungssystem in beiden Scripts
- ✅ RegisterController mit Validierung
- ✅ Register View mit modernem Gradient-Design (Purple/Blue)
- ✅ Login View mit "Register here" Link
- ✅ Routes: `/auth/register` (GET + POST)
- ✅ `.env` Konfiguration: `REGISTRATION_ENABLED=true`

#### 2. **APP_URL Konfiguration**
- ✅ **install.sh**: Fragt Panel-URL während Installation ab
- ✅ **update-techtyl.sh**: Ermöglicht APP_URL Update
- ✅ Automatische IP-Erkennung als Standard
- ✅ Manuelle URL-Eingabe möglich
- ✅ APP_URL wird korrekt in `.env` gesetzt

#### 3. **Branding & Design**
- ✅ Footer "🦕 Techtyl - based on Pterodactyl Panel" im Hauptpanel
- ✅ Moderne Login/Register Seiten mit Gradient-Design
- ✅ Purple/Blue Theme (#667eea → #764ba2)
- ✅ Responsive Design für Mobile
- ✅ Konsistentes Branding in allen Views

---

## 📝 Geänderte Dateien

### **install.sh**
```bash
Zeile 881-889: Footer Branding hinzugefügt
- Fügt "based on Pterodactyl" Footer zu wrapper.blade.php hinzu
- Prüft ob bereits vorhanden (idempotent)
```

### **update-techtyl.sh**
```bash
Zeile 41-70: APP_URL Konfiguration (NEU)
- Step 0/5: APP_URL Update-Funktionalität
- Zeigt aktuelle APP_URL
- Ermöglicht Änderung oder Beibehaltung
- Aktualisiert .env automatisch

Zeile 76: Step 1/4 → 1/5 (angepasst)
Zeile 329: Step 2/4 → 2/5 (angepasst)
Zeile 476: Step 3/4 → 3/5 (angepasst)
Zeile 490: Step 4/4 → 4/5 (angepasst)

Zeile 514-542: Step 5/5 - Verbesserter Abschluss (NEU)
- Zeigt aktualisierte PANEL_URL
- Klare Links zu Registrierung und Login
- Strukturierte Ausgabe
```

---

## 🔧 Technische Details

### **Registrierung Features**
- **Validation**: Username (alphanumeric + _-), Email, Password (min 8 chars)
- **Password Confirmation**: Muss übereinstimmen
- **UUID Generation**: Automatisch für neue User
- **Auto-Login**: Nach erfolgreicher Registrierung
- **Security**: CSRF Protection, Input Sanitization, XSS Protected

### **APP_URL Handling**
- **Installation**: Interaktive Abfrage mit IP-Autodetect
- **Update**: Zeigt aktuelle URL, ermöglicht Änderung
- **Validation**: Wird in `.env` korrekt gesetzt
- **Propagation**: Cache wird nach Änderung neu gebaut

### **Design System**
- **Colors**:
  - Primary: `#667eea` (Purple)
  - Secondary: `#764ba2` (Dark Purple)
  - Text: `#2d3748` (Dark Gray)
  - Light: `#718096` (Medium Gray)
- **Typography**: System Font Stack (Apple/Segoe UI/Roboto)
- **Border Radius**: 8-12px für moderne Optik
- **Shadows**: `0 10px 40px rgba(0,0,0,0.1)`

---

## 📊 Script-Struktur

### **install.sh**
```
[1/9] System-Vorbereitung
[2/9] MariaDB Setup
[3/9] Pterodactyl Installation
[4/9] Admin Account
[5/9] Azure OpenAI Konfiguration
[6/9] Techtyl AI Features
[7/9] User-Registrierung + Footer Branding
[8/9] Webserver & Services
[9/9] Abschluss
```

### **update-techtyl.sh**
```
[0/5] APP_URL Konfiguration (NEU!)
[1/5] User-Registrierung aktivieren
[2/5] Login View anpassen
[3/5] Footer Branding hinzufügen
[4/5] System aktualisieren (Cache/Permissions)
[5/5] Abschluss (NEU!)
```

---

## ✅ Testing Checklist

Nach Installation/Update prüfen:

- [ ] Panel ist erreichbar unter APP_URL
- [ ] Login funktioniert
- [ ] Register-Link auf Login-Seite sichtbar
- [ ] Registrierung funktioniert (`/auth/register`)
- [ ] Neuer User kann sich einloggen
- [ ] Footer "based on Pterodactyl" ist sichtbar
- [ ] Design ist korrekt (Purple Gradient)
- [ ] APP_URL ist korrekt in `.env`

---

## 🚀 Deployment

### **Neue Installation**
```bash
curl -sSL https://raw.githubusercontent.com/theredstonee/Techtyl/main/install.sh | sudo bash
```

### **Bestehendes System aktualisieren**
```bash
curl -sSL https://raw.githubusercontent.com/theredstonee/Techtyl/main/update-techtyl.sh | sudo bash
```

### **APP_URL ändern**
```bash
cd /var/www/pterodactyl
sudo nano .env
# APP_URL=https://neue-domain.de
php artisan config:clear
php artisan config:cache
systemctl restart php8.2-fpm nginx
```

---

## 📚 Dokumentation

- **README.md**: Vollständige englische Dokumentation
- **install.sh**: Kommentiert und strukturiert
- **update-techtyl.sh**: Schritt-für-Schritt Anleitung

---

## 🎯 Nächste Schritte

### **Geplante Features (Optional)**
1. **AI Frontend Integration**
   - React Component für AI Chat
   - Server-Erstellungs-Assistent
   - Ressourcen-Empfehlungen UI

2. **Erweiterte User-Features**
   - Email-Verifikation
   - Password-Reset
   - User-Profile bearbeiten

3. **Admin-Features**
   - User-Limits über Admin-Panel setzen
   - Registrierung aktivieren/deaktivieren (GUI)
   - Usage Statistics Dashboard

---

## 🤝 Contributing

Pull Requests willkommen!

Repository: https://github.com/theredstonee/Techtyl

---

## 📄 License

MIT License - basierend auf Pterodactyl Panel

---

**🦕 Techtyl - AI-Powered Game Server Management**

_based on Pterodactyl Panel_
