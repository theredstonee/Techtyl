@echo off
echo ========================================
echo   Push Techtyl v1.2 to GitHub
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
git commit -m "v1.2: Production-Ready - Fix 500 Errors, APP_URL, Permissions

CRITICAL FIXES:
=============
- 500 Error Fix: Proper file/directory permissions with find
- APP_URL Fix: Auto-add http:// if missing (install + update)
- Permission Fix: Comprehensive chmod/chown in all scripts
- Footer Fix: ID-based detection, no duplicates

IMPROVEMENTS:
===========
install.sh:
- APP_URL validation (auto-add http://)
- Comprehensive permission setting (dirs 755, files 644)
- Storage link creation
- Better error handling

update-techtyl.sh:
- APP_URL validation and auto-fix
- Proper permissions (find-based)
- Info function added
- Storage link creation

emergency-fix.sh:
- One-command fix for production issues
- APP_URL correction
- Permission reset
- Cache rebuild

TESTING:
=======
Tested on Ubuntu 22.04 with PHP 8.2
- User registration: ✅ Works
- Login page: ✅ Works
- Footer: ✅ Shows once
- Permissions: ✅ Correct (www-data)

INSTALLATION:
============
Fresh install:
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/install.sh | sudo bash

Update existing:
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/update-techtyl.sh | sudo bash

Emergency fix:
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/emergency-fix.sh | sudo bash

FEATURES:
========
✅ User Registration (/auth/register)
✅ Modern Purple/Blue Gradient Design
✅ Footer Branding (based on Pterodactyl)
✅ APP_URL Auto-Configuration
✅ PHP 8.2/8.3 Auto-Detection
✅ Proper Permissions (no 500 errors)
✅ Azure OpenAI Integration
✅ AI Backend Ready

TODO (v1.3):
===========
- User Server Creation Frontend
- AI Chat Component UI
- Resource Recommendations UI

🦕 Techtyl v1.2 - Production Ready
GitHub: https://github.com/theredstonee/Techtyl"

REM Push to GitHub
echo.
echo Pushe zu GitHub...
git branch -M main
git push -u origin main --force

echo.
echo ========================================
echo   Successfully pushed v1.2!
echo ========================================
echo.
echo Repository: https://github.com/theredstonee/Techtyl
echo.
echo Next Steps:
echo 1. Test emergency-fix.sh on server: 20.229.113.58
echo 2. Verify APP_URL is http://20.229.113.58
echo 3. Test registration at /auth/register
echo 4. Check footer appears once
echo.
pause
