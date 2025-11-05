@echo off
chcp 65001 >nul
echo.
echo ╔════════════════════════════════════════════════════════════════╗
echo ║                                                                ║
echo ║           🚀 TECHTYL - Konfigurations-Hilfe 🚀                ║
echo ║                                                                ║
echo ╚════════════════════════════════════════════════════════════════╝
echo.
echo.

echo ⚙️  SCHRITT 1: Datenbank-Verbindung
echo ═══════════════════════════════════════════════════════════
echo.
echo Die .env Datei wurde erstellt. Du musst folgende Felder ausfüllen:
echo.
echo 📦 1. DB_PASSWORD=dein_mysql_passwort_hier_eintragen
echo    ^|
echo    ^└─► Dein MySQL/MariaDB Root-Passwort eintragen
echo.
echo Beispiel:
echo   DB_PASSWORD=MeinSicheresPasswort123
echo.
pause
echo.

echo 🤖 SCHRITT 2: Claude AI API Key (WICHTIG!)
echo ═══════════════════════════════════════════════════════════
echo.
echo Ohne diesen Key funktioniert die AI nicht!
echo.
echo 1. Öffne: https://console.anthropic.com/
echo 2. Registriere dich oder melde dich an
echo 3. Gehe zu "API Keys"
echo 4. Erstelle einen neuen Key
echo 5. Kopiere den Key (beginnt mit: sk-ant-...)
echo.
echo In .env eintragen:
echo   CLAUDE_API_KEY=sk-ant-dein-key-hier
echo.
echo 💡 TIPP: Neukunden bekommen oft $5-10 Startguthaben!
echo.
pause
echo.

echo 📝 SCHRITT 3: Optionale Einstellungen
echo ═══════════════════════════════════════════════════════════
echo.
echo Diese Einstellungen kannst du später ändern:
echo.
echo • TECHTYL_REGISTRATION_ENABLED=true
echo   └─► Dürfen sich Benutzer selbst registrieren?
echo.
echo • TECHTYL_EMAIL_VERIFICATION=false
echo   └─► E-Mail-Bestätigung erforderlich?
echo       (Für Entwicklung auf false lassen!)
echo.
echo • TECHTYL_DEFAULT_SERVER_LIMIT=3
echo   └─► Wie viele Server pro Benutzer?
echo.
echo • APP_DEBUG=true
echo   └─► Debug-Modus (für Entwicklung: true)
echo.
pause
echo.

echo ✅ SCHRITT 4: .env Datei jetzt bearbeiten
echo ═══════════════════════════════════════════════════════════
echo.
echo Die .env Datei wird jetzt geöffnet.
echo Bitte trage folgendes ein:
echo.
echo   ✓ DB_PASSWORD (dein MySQL-Passwort)
echo   ✓ CLAUDE_API_KEY (von console.anthropic.com)
echo.
echo Speichern mit: Strg+S
echo Schließen mit: Alt+F4
echo.
pause

notepad backend\.env

echo.
echo 🎉 FERTIG!
echo ═══════════════════════════════════════════════════════════
echo.
echo Nächste Schritte:
echo.
echo 1. Datenbank erstellen (falls noch nicht geschehen):
echo    mysql -u root -p
echo    CREATE DATABASE techtyl;
echo    EXIT;
echo.
echo 2. Setup ausführen:
echo    setup.bat
echo.
echo 3. Techtyl starten:
echo    start.bat
echo.
echo 📚 Hilfe: Siehe QUICKSTART.md
echo.
pause
