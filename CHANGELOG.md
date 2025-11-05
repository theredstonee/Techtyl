# Changelog

All notable changes to Techtyl will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2025-01-05

### Fixed
- **500 Internal Server Error** - Fixed file and directory permissions (755/644)
- **APP_URL validation** - Auto-adds `http://` if missing during installation
- **Multiple footer display** - Footer now appears only once using ID-based detection
- **Permission issues** - Comprehensive `find`-based chmod/chown implementation

### Added
- Automatic PHP version detection (8.2/8.3) based on Ubuntu version
- APP_URL validation in both `install.sh` and `update-techtyl.sh`
- Storage link creation during installation
- Improved error messages and status output

### Changed
- Updated installation workflow to validate URL format
- Enhanced permission setting with separate directory/file handling
- Improved service restart logic with PHP version detection

## [1.1.0] - 2025-01-04

### Added
- User registration system (`/auth/register`)
- Modern login/register pages with purple-blue gradient design
- Footer branding "based on Pterodactyl Panel"
- APP_URL configuration during installation
- Azure OpenAI backend integration

### Features
- **User Registration** - Users can create accounts without admin intervention
- **Modern UI** - Purple/blue gradient theme with responsive design
- **AI Backend** - Azure OpenAI GPT-4o integration ready
- **Auto-Configuration** - Guided setup during installation

## [1.0.0] - 2025-01-03

### Added
- Initial release
- Pterodactyl Panel installation script
- Azure OpenAI integration
- One-command installation via wget
- Ubuntu 22.04/24.04 support
- PHP 8.2/8.3 support
- MariaDB database setup
- Nginx web server configuration
- Redis caching
- Queue worker service (pteroq)

### Features
- **One-Command Install** - Simple wget-based installation
- **AI Integration** - Azure OpenAI for intelligent server management
- **Modern Stack** - PHP 8.2+, MariaDB, Nginx, Redis
- **Production Ready** - Systemd services, proper permissions

---

## Upgrade Instructions

### From 1.1.0 to 1.2.0

```bash
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/update-techtyl.sh | sudo bash
```

This will:
- Fix APP_URL format
- Set proper permissions
- Update cache
- Restart services

### From 1.0.0 to 1.2.0

**Recommended:** Clean installation

```bash
# Backup first
sudo cp /var/www/pterodactyl/.env /root/pterodactyl-env.backup

# Clean install
sudo rm -rf /var/www/pterodactyl
sudo mysql -e "DROP DATABASE IF EXISTS panel;"

# Install latest
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/install.sh | sudo bash
```

---

## Support

- **Issues:** [GitHub Issues](https://github.com/theredstonee/Techtyl/issues)
- **Documentation:** [README.md](README.md)

---

[1.2.0]: https://github.com/theredstonee/Techtyl/releases/tag/v1.2.0
[1.1.0]: https://github.com/theredstonee/Techtyl/releases/tag/v1.1.0
[1.0.0]: https://github.com/theredstonee/Techtyl/releases/tag/v1.0.0
