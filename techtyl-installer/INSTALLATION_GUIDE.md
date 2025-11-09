# Techtyl Installer - Installation Guide

## Overview

The Techtyl Installer has been successfully created based on the Pterodactyl installer. This is a customized version with Techtyl branding while maintaining all core functionality.

## What Has Been Created

### ✓ Core Files
- `install.sh` - Main installation script with interactive menu
- `lib/lib.sh` - Core library with helper functions, system checks, and visual elements
- `ui/panel.sh` - Panel installation UI (user input collection)
- `README.md` - Comprehensive documentation
- `INSTALLATION_GUIDE.md` - This guide

### 🎨 Key Customizations

1. **Branding**
   - All "Pterodactyl" references changed to "Techtyl" in user-facing messages
   - Custom ASCII art logo in welcome screen
   - Updated color scheme with enhanced visual feedback
   - Custom copyright notices preserving original attribution

2. **Visual Enhancements**
   - Colorful welcome banner with TECHTYL ASCII art
   - Enhanced success (✓), error (✗), warning (⚠), and info (ℹ) symbols
   - Improved progress indicators
   - Better formatted output messages

3. **Configuration Changes**
   - Default database user: `techtyl` (instead of `pterodactyl`)
   - Default timezone: `Europe/Berlin` (instead of `Europe/Stockholm`)
   - Log path: `/var/log/techtyl-installer.log`
   - GitHub URLs updated for Techtyl repository

## How to Use

### Quick Start

1. **Make the installer executable:**
   ```bash
   chmod +x install.sh
   ```

2. **Run the installer as root:**
   ```bash
   sudo ./install.sh
   ```

3. **Follow the interactive prompts:**
   - Choose installation type (Panel, Wings, or Both)
   - Configure database settings
   - Set up admin account
   - Configure SSL/Let's Encrypt
   - Set firewall rules

### Installation Options

The installer provides these options:

```
[0] Install the Techtyl panel
[1] Install Techtyl Wings
[2] Install both [0] and [1] on the same machine
[3] Install panel with development version (may be unstable)
[4] Install Wings with development version (may be unstable)
[5] Install both with development version
[6] Uninstall panel or wings with development version
```

### System Requirements

- **Supported OS:**
  - Ubuntu 22.04, 24.04
  - Debian 10, 11, 12, 13
  - Rocky Linux 8, 9
  - AlmaLinux 8, 9

- **Minimum Requirements:**
  - 2GB RAM
  - 10GB disk space
  - Root access
  - curl installed

### Online Installation

For remote installation directly from GitHub (once repository is set up):

```bash
bash <(curl -s https://raw.githubusercontent.com/techtyl/techtyl-installer/main/install.sh)
```

## Remaining Work

To complete the full installer, the following files need to be adapted from the original:

### Priority 1 - Essential Files
- `ui/wings.sh` - Wings installation UI
- `ui/uninstall.sh` - Uninstallation UI
- `installers/panel.sh` - Panel installation logic
- `installers/wings.sh` - Wings installation logic
- `installers/uninstall.sh` - Uninstallation logic

### Priority 2 - Additional Features
- `installers/phpmyadmin.sh` - Optional phpMyAdmin installation
- `lib/verify-fqdn.sh` - FQDN verification script
- `configs/` directory - Configuration templates (nginx, systemd, etc.)

### Priority 3 - Utilities
- `scripts/release.sh` - Release automation
- `LICENSE` - License file (GPL v3)
- `CHANGELOG.md` - Version history
- `CONTRIBUTING.md` - Contribution guidelines

## How to Complete Remaining Files

### Option 1: Automatic Adaptation

Use the provided Python script to copy and adapt all remaining files:

```python
# The copy_and_adapt.py script in the root directory can process
# all remaining files automatically
python copy_and_adapt.py
```

### Option 2: Manual Adaptation

For each remaining file:

1. Copy from `pterodactyl-installer-master/pterodactyl-installer-master/`
2. Update the header with Techtyl attribution
3. Replace user-facing "Pterodactyl" text with "Techtyl"
4. Update GitHub URLs
5. Preserve core functionality and technical paths

### Key Replacement Rules

**DO Replace:**
- User-facing messages: "Pterodactyl" → "Techtyl"
- Variable names: `PTERODACTYL_*` → `TECHTYL_*`
- GitHub URLs: `pterodactyl-installer` → `techtyl-installer`
- Log paths: `/var/log/pterodactyl-installer.log` → `/var/log/techtyl-installer.log`

**DON'T Replace:**
- System paths: `/var/www/pterodactyl` (used by actual software)
- Software repositories: `pterodactyl/panel`, `pterodactyl/wings` (we use official Pterodactyl software)
- Technical commands and configurations

## Testing

Before deploying to production:

1. **Test in a VM:**
   ```bash
   # Use Vagrant or a test VM
   vagrant up ubuntu_jammy
   vagrant ssh ubuntu_jammy
   cd /vagrant
   sudo ./install.sh
   ```

2. **Verify All Options:**
   - Test panel installation
   - Test wings installation
   - Test combined installation
   - Test uninstallation

3. **Check Logs:**
   ```bash
   tail -f /var/log/techtyl-installer.log
   ```

## Deployment

1. **Create GitHub Repository:**
   ```bash
   cd techtyl-installer
   git init
   git add .
   git commit -m "Initial commit: Techtyl installer v1.0.0"
   git remote add origin https://github.com/techtyl/techtyl-installer.git
   git push -u origin main
   ```

2. **Test Online Installation:**
   ```bash
   bash <(curl -s https://raw.githubusercontent.com/techtyl/techtyl-installer/main/install.sh)
   ```

3. **Update Documentation:**
   - Add installation instructions to main Techtyl repository
   - Create video tutorials if needed
   - Update support documentation

## Maintenance

### Updating Versions

Edit `install.sh` to update version:
```bash
export SCRIPT_RELEASE="v1.4.0"  # Update this
```

### Adding Features

1. Add functionality to appropriate file (`ui/`, `installers/`, or `lib/`)
2. Test thoroughly
3. Update CHANGELOG.md
4. Create new release tag

## Support

- **Issues:** https://github.com/techtyl/techtyl-installer/issues
- **Main Project:** https://github.com/techtyl/techtyl
- **Original Pterodactyl:** https://pterodactyl.io

## Credits

Based on the excellent work by:
- Vilhelm Prytz (original pterodactyl-installer creator)
- Linux123123 (maintainer)
- The Pterodactyl community

Adapted for Techtyl with custom branding and enhancements.

## License

GNU General Public License v3.0 - see LICENSE file for details.

Original work Copyright (C) 2018-2025 Vilhelm Prytz
Techtyl Adaptation Copyright (C) 2025
