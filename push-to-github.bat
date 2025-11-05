@echo off
echo ========================================
echo   Push Techtyl Updates zu GitHub
echo ========================================
echo.

cd /d E:\Claude\Techtyl

REM Check if git repo exists
if not exist ".git" (
    echo Initialisiere Git Repository...
    git init
    git remote add origin https://github.com/theredstonee/Techtyl.git
    echo Git Repository initialisiert!
    echo.
)

REM Add all files
echo Fuege Dateien hinzu...
git add .

REM Commit changes
echo Erstelle Commit...
git commit -m "Fix: Multiple Footer, Register 500, Auto PHP Detection

FIXES:
- Footer Bug: Nur noch 1x anzeigen (ID-basiert)
- Register 500: Bessere Route-Registrierung mit Namespace
- PHP Version: Auto-Detection (8.2/8.3) in allen Scripts
- README: Englisch mit wget statt curl
- Quick-Fix Script: Behebt alle bekannten Probleme

SCRIPTS:
- install.sh: Auto PHP Version Detection
- update-techtyl.sh: APP_URL Update-Funktion
- quick-fix.sh: One-Command Fix für 500/Footer
- fix-permissions.sh: PHP Version Support

FEATURES READY:
✅ User Registration (/auth/register)
✅ Modern Design (Purple/Blue Gradient)
✅ Footer Branding 'based on Pterodactyl'
✅ APP_URL Configuration
✅ Auto PHP 8.2/8.3 Support

TODO (Next Release):
- User Server Creation
- KI Frontend Integration
- AI Chat Component

🦕 Techtyl v1.1
Generated with Claude Code
https://claude.com/claude-code"

REM Push to GitHub
echo.
echo Pushe zu GitHub...
git branch -M main
git push -u origin main --force

echo.
echo ========================================
echo   Erfolgreich zu GitHub gepusht!
echo ========================================
echo.
echo Repository: https://github.com/theredstonee/Techtyl
echo.
echo Naechste Schritte:
echo 1. Teste quick-fix.sh auf Server
echo 2. Pruefe ob Footer nur 1x angezeigt wird
echo 3. Teste Registrierung /auth/register
echo.
pause
