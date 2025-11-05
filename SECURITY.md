# 🔒 Security Policy

## Reporting Security Vulnerabilities

Wir nehmen die Sicherheit von Techtyl sehr ernst. Wenn du eine Sicherheitslücke findest, melde sie bitte verantwortungsvoll.

### 📧 Kontakt

**Bitte NICHT öffentlich als Issue melden!**

Stattdessen:
- E-Mail: security@techtyl.io (bevorzugt)
- Oder: Private Security Advisory auf GitHub

### 🔍 Was zu melden ist

- Authentifizierungs-/Autorisierungs-Schwachstellen
- XSS, CSRF, SQL Injection
- Remote Code Execution
- Sensible Daten-Leaks
- API-Missbrauch

### ✅ Was wir tun

1. **Bestätigung** innerhalb von 48 Stunden
2. **Analyse** der Schwachstelle
3. **Fix** und Tests
4. **Benachrichtigung** bei Veröffentlichung
5. **Credit** im Changelog (falls gewünscht)

### 🛡️ Sicherheits-Features

Techtyl implementiert:

#### Frontend
- ✅ XSS-Schutz via DOMPurify
- ✅ Content Security Policy (CSP)
- ✅ Input-Validierung
- ✅ Security Headers

#### Backend
- ✅ XSS-Protection Middleware
- ✅ CSRF-Schutz (Laravel Sanctum)
- ✅ SQL Injection-Schutz (Eloquent ORM)
- ✅ Bcrypt Password-Hashing
- ✅ Rate Limiting
- ✅ Input-Validierung

#### API
- ✅ Token-basierte Authentifizierung
- ✅ API Rate Limiting
- ✅ Request Sanitization

### 📝 Best Practices für Deployment

#### Niemals in Git committen:
- ❌ API Keys
- ❌ Passwörter
- ❌ .env Dateien mit echten Credentials
- ❌ Private Keys
- ❌ Datenbank-Dumps

#### Immer verwenden:
- ✅ Umgebungsvariablen (.env)
- ✅ Starke Passwörter
- ✅ HTTPS/SSL
- ✅ Firewall
- ✅ Regelmäßige Updates

### 🔐 Credentials sicher aufbewahren

**WICHTIG für Azure OpenAI:**

```bash
# ✅ RICHTIG: In .env (nicht in Git)
AZURE_OPENAI_API_KEY=dein-key-hier

# ❌ FALSCH: Fest im Code
$apiKey = 'abc123...';
```

**Auf dem Server:**
```bash
# .env vor Git schützen
chmod 600 backend/.env
chown www-data:www-data backend/.env
```

### 🚨 Bei API Key Leak

**Falls dein API Key öffentlich wurde:**

1. **SOFORT** in Azure Portal:
   - Keys and Endpoint → "Regenerate Key"

2. **Neuen Key** in .env eintragen

3. **Alten Key** ist jetzt ungültig

4. **Git History** bereinigen (falls Key committed):
   ```bash
   git filter-branch --force --index-filter \
     "git rm --cached --ignore-unmatch backend/.env" \
     --prune-empty --tag-name-filter cat -- --all
   ```

### 📋 Security Checklist

#### Vor Deployment:
- [ ] `.env` nicht in Git
- [ ] `.gitignore` korrekt konfiguriert
- [ ] Starke DB-Passwörter
- [ ] API Keys regeneriert
- [ ] SSL/HTTPS aktiviert
- [ ] Firewall konfiguriert

#### Regelmäßig:
- [ ] System-Updates (`apt update && apt upgrade`)
- [ ] Dependency-Updates (`composer update`, `npm update`)
- [ ] Log-Monitoring
- [ ] Backup-Tests

### 🔄 Patch-Policy

- **Kritische Sicherheitslücken**: Fix innerhalb 24-48h
- **Mittlere Schwachstellen**: Fix innerhalb 7 Tagen
- **Niedrige Schwachstellen**: Fix im nächsten Release

### 📚 Weitere Ressourcen

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [Azure Security Best Practices](https://learn.microsoft.com/en-us/azure/security/)

### 🏆 Hall of Fame

Vielen Dank an alle, die verantwortungsvoll Sicherheitslücken gemeldet haben!

---

**Zuletzt aktualisiert**: 2024

**Version**: 1.0.0
