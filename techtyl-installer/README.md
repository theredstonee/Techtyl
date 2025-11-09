# Techtyl Installer

[![License: GPL v3](https://img.shields.io/github/license/techtyl/techtyl-installer)](LICENSE)

Automated installation scripts for Techtyl Panel & Wings. Based on the excellent [Pterodactyl Installer](https://github.com/pterodactyl-installer/pterodactyl-installer) by Vilhelm Prytz.

## About Techtyl

Techtyl is a customized version of Pterodactyl Panel, featuring:
- Custom branding and visual elements
- Enhanced security features
- Integrated Azure OpenAI support
- Maintained core Pterodactyl functionality

## Features

- **Automatic Panel Installation**: Dependencies, database, cronjob, nginx
- **Automatic Wings Installation**: Docker, systemd
- **Optional Let's Encrypt**: Automatic SSL certificate configuration
- **Firewall Configuration**: Automatic UFW/firewall-cmd setup
- **Uninstallation Support**: For both panel and wings

## Supported Operating Systems

| Operating System | Version | Supported          | PHP Version |
| ---------------- | ------- | ------------------ | ----------- |
| Ubuntu           | 22.04   | ✓                  | 8.3         |
|                  | 24.04   | ✓                  | 8.3         |
| Debian           | 10      | ✓                  | 8.3         |
|                  | 11      | ✓                  | 8.3         |
|                  | 12      | ✓                  | 8.3         |
|                  | 13      | ✓                  | 8.3         |
| Rocky Linux      | 8       | ✓                  | 8.3         |
|                  | 9       | ✓                  | 8.3         |
| AlmaLinux        | 8       | ✓                  | 8.3         |
|                  | 9       | ✓                  | 8.3         |

## Installation

To use the installation script, run this command as root:

```bash
bash <(curl -s https://raw.githubusercontent.com/techtyl/techtyl-installer/main/install.sh)
```

**Note**: On some systems, you must be logged in as root before executing the command (using `sudo` may not work).

### Installation Options

The script will present you with several options:

1. **Install the Techtyl panel** - Installs only the web panel
2. **Install Techtyl Wings** - Installs only the Wings daemon
3. **Install both** - Installs both panel and wings on the same machine

### Firewall Setup

The installation script can automatically configure your firewall. It is highly recommended to enable automatic firewall configuration during installation.

## Manual Installation

If you prefer to run the installer manually or locally:

1. Clone this repository:
```bash
git clone https://github.com/techtyl/techtyl-installer.git
cd techtyl-installer
```

2. Make the script executable:
```bash
chmod +x install.sh
```

3. Run the installer:
```bash
./install.sh
```

## Requirements

- A fresh installation of a supported operating system
- Root access
- At least 2GB of RAM
- At least 10GB of disk space
- curl installed (`apt install curl` or `dnf install curl`)

## Post-Installation

After installation, you can access your Techtyl panel at:
```
https://your-domain.com
```

Use the admin credentials you created during installation to log in.

## Security Considerations

- Always use HTTPS (Let's Encrypt is configured automatically)
- Keep your system updated
- Use strong passwords
- Configure firewall properly
- Regular backups are recommended

## Troubleshooting

### Installation Fails

Check the installation log:
```bash
cat /var/log/techtyl-installer.log
```

### Port 80/443 Already in Use

Make sure no other web server (Apache, nginx) is running:
```bash
systemctl stop apache2  # or nginx
systemctl disable apache2  # or nginx
```

### Database Connection Issues

Verify MariaDB is running:
```bash
systemctl status mariadb
```

## Credits

This installer is based on the [Pterodactyl Installer](https://github.com/pterodactyl-installer/pterodactyl-installer) created by:
- [Vilhelm Prytz](https://github.com/vilhelmprytz)
- [Linux123123](https://github.com/Linux123123)

**Techtyl Adaptation**: Modified and branded for Techtyl with additional features and customizations.

## License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.

## Support

For issues related to:
- **Techtyl Panel**: [Techtyl GitHub Issues](https://github.com/techtyl/techtyl/issues)
- **This Installer**: [Installer GitHub Issues](https://github.com/techtyl/techtyl-installer/issues)
- **Original Pterodactyl**: [Pterodactyl Documentation](https://pterodactyl.io/panel/1.0/getting_started.html)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Disclaimer

This script is provided "as is" without warranty of any kind. Always review scripts before running them on your system.
