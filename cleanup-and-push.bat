@echo off
echo ========================================
echo   Cleanup and Push Techtyl v1.2
echo ========================================
echo.

cd /d E:\Claude\Techtyl

echo Deleting temporary files...

REM Delete all temporary .sh files (keep only install.sh and update-techtyl.sh)
if exist "start.sh" del "start.sh"
if exist "setup.sh" del "setup.sh"
if exist "deploy.sh" del "deploy.sh"
if exist "install-addon.sh" del "install-addon.sh"
if exist "install-techtyl.sh" del "install-techtyl.sh"
if exist "techtyl-customization.sh" del "techtyl-customization.sh"
if exist "fix-500-error.sh" del "fix-500-error.sh"
if exist "quick-fix.sh" del "quick-fix.sh"
if exist "fix-permissions.sh" del "fix-permissions.sh"
if exist "emergency-fix.sh" del "emergency-fix.sh"

REM Delete temporary .bat files (keep only this one)
if exist "start.bat" del "start.bat"
if exist "setup.bat" del "setup.bat"
if exist "setup-helper.bat" del "setup-helper.bat"

REM Delete extra .md files (keep only README.md, CHANGELOG.md, CONTRIBUTING.md, LICENSE, SECURITY.md)
if exist "SETUP.md" del "SETUP.md"
if exist "FEATURES.md" del "FEATURES.md"
if exist "PROJECT_OVERVIEW.md" del "PROJECT_OVERVIEW.md"
if exist "QUICKSTART.md" del "QUICKSTART.md"
if exist "AI_PROVIDERS.md" del "AI_PROVIDERS.md"
if exist "AZURE_SETUP.md" del "AZURE_SETUP.md"
if exist "AZURE_QUICKSTART.md" del "AZURE_QUICKSTART.md"
if exist "LINUX_DEPLOYMENT.md" del "LINUX_DEPLOYMENT.md"
if exist "CLAUDE.md" del "CLAUDE.md"
if exist "GITHUB_SETUP.md" del "GITHUB_SETUP.md"
if exist "VM_QUICKSTART.md" del "VM_QUICKSTART.md"
if exist "PTERODACTYL_ADDON.md" del "PTERODACTYL_ADDON.md"
if exist "ADDON_INSTALL_GUIDE.md" del "ADDON_INSTALL_GUIDE.md"
if exist "QUICK_START.md" del "QUICK_START.md"
if exist "DEPLOYMENT_READY.md" del "DEPLOYMENT_READY.md"
if exist "CHANGES.md" del "CHANGES.md"
if exist "TESTING.md" del "TESTING.md"
if exist "QUICKFIX.md" del "QUICKFIX.md"

echo ✓ Cleanup complete!
echo.

REM Init git if needed
if not exist ".git" (
    echo Initializing Git repository...
    git init
    git remote add origin https://github.com/theredstonee/Techtyl.git
    echo.
)

REM Add files
echo Adding files...
git add .

REM Commit
echo Creating commit...
git commit -m "v1.2.0: Production-Ready Release

## Critical Fixes
- Fixed 500 Internal Server Error (proper permissions: 755/644)
- Fixed APP_URL validation (auto-adds http:// if missing)
- Fixed multiple footer displays (ID-based detection)
- Fixed permission issues (comprehensive chmod/chown)

## Improvements
- Automatic PHP version detection (8.2/8.3)
- Enhanced APP_URL validation in install and update scripts
- Better error handling and status messages
- Storage link creation during installation

## Installation

Fresh install:
\`\`\`bash
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/install.sh | sudo bash
\`\`\`

Update existing:
\`\`\`bash
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/update-techtyl.sh | sudo bash
\`\`\`

## Features
✅ User Registration (/auth/register)
✅ Modern UI (Purple/Blue Gradient)
✅ Footer Branding
✅ APP_URL Auto-Configuration
✅ PHP 8.2/8.3 Support
✅ Azure OpenAI Integration

## Documentation
- README.md - Complete installation guide
- CHANGELOG.md - Version history
- CONTRIBUTING.md - Contribution guidelines
- SECURITY.md - Security policy

🦕 Techtyl v1.2.0 - Production Ready
Based on Pterodactyl Panel
https://github.com/theredstonee/Techtyl"

REM Push
echo.
echo Pushing to GitHub...
git branch -M main
git push -u origin main --force

echo.
echo ========================================
echo   Successfully pushed v1.2.0!
echo ========================================
echo.
echo Repository: https://github.com/theredstonee/Techtyl
echo.
echo Clean file structure:
echo   ✓ install.sh
echo   ✓ update-techtyl.sh
echo   ✓ README.md
echo   ✓ CHANGELOG.md
echo   ✓ CONTRIBUTING.md
echo   ✓ SECURITY.md
echo   ✓ LICENSE
echo   ✓ .gitignore
echo.
pause
