@echo off
echo ========================================
echo   Pushe Techtyl zu GitHub
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
git commit -m "Update: Registrierung, APP_URL Konfiguration, Footer Branding

- install.sh: Registrierung, Footer Branding, APP_URL Abfrage
- update-techtyl.sh: APP_URL Update-Funktion, alle Features
- Beide Scripts komplett mit User-Registration
- Login/Register Views mit modernem Design
- Footer 'based on Pterodactyl' im Hauptpanel

🦕 Generated with Claude Code
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
pause
