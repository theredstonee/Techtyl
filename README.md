# 🦕 Techtyl

<div align="center">

![Pterodactyl](https://img.shields.io/badge/Based_on-Pterodactyl-0e4688?style=for-the-badge&logo=pterodactyl)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Azure OpenAI](https://img.shields.io/badge/Azure_OpenAI-GPT--4o-0078D4?style=for-the-badge&logo=microsoft-azure)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge)](LICENSE)

**Pterodactyl Panel with AI-Powered Server Management**

[Features](#-features) • [Installation](#-installation) • [Documentation](#-documentation) • [Support](#-support)

</div>

---

## ⚡ Quick Start

```bash
# Install Techtyl on fresh Ubuntu 22.04/24.04
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/install.sh | sudo bash  wget https://raw.githubusercontent.com/theredstonee/Techtyl/main/install.sh
```

**That's it!** The installer will guide you through the setup.

---

## 📖 What is Techtyl?

Techtyl is a **complete game server management panel** built on Pterodactyl with integrated AI capabilities. It provides all the power of Pterodactyl Panel enhanced with Azure OpenAI for intelligent server configuration, troubleshooting, and management.

### 🎯 Key Features

- 🤖 **AI Assistant** for smart server configuration
- 👥 **User Registration** - users can create accounts without admin
- 🎮 **User Server Creation** - users can create their own servers (with limits)
- 💡 **Automatic Recommendations** based on game type
- 🏷️ **Name Generation** for creative server names
- 🔧 **Troubleshooting Help** when problems occur
- 📊 **Resource Optimization** with AI suggestions

---

## ✨ Features

### 🤖 AI-Powered Server Creation

- **Intelligent Resource Recommendations**: AI suggests optimal CPU, RAM, and Disk based on game type
- **Automatic Naming**: Generate creative and suitable server names
- **Interactive Assistant**: Ask questions during server setup
- **Context-Aware**: Considers player count, mods, requirements, etc.

### 👥 User Management

- **Self-Registration**: Users can create accounts without admin intervention
- **Server Limits**: Configurable per-user server limits (default: 3 servers)
- **Resource Limits**: Set maximum CPU, RAM, and disk per user
- **AI Access**: All users have access to AI features

### 🎮 Multi-Game Support

Optimized for:
- Minecraft (Java & Bedrock)
- Rust
- ARK: Survival Evolved
- Counter-Strike (CS:GO, CS2)
- Valheim
- Terraria
- TeamSpeak
- Discord Bots
- And more...

### 🔒 Security & Privacy

- ✅ **GDPR Compliant**: Azure EU servers
- ✅ **Secure by Default**: Built on Pterodactyl's security model
- ✅ **Rate Limiting**: Protection against abuse
- ✅ **Input Sanitization**: XSS/CSRF protected
- ✅ **Encrypted Credentials**: Secure storage of sensitive data

---

## 🚀 Installation

### Prerequisites

- ✅ Fresh Ubuntu 22.04 or 24.04 server
- ✅ 2+ CPU cores, 4GB+ RAM, 20GB+ disk
- ✅ Root access (SSH)
- ✅ Azure OpenAI account with GPT-4o deployment

### One-Command Installation (15-20 minutes)

```bash
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/install.sh | sudo bash
```

**Alternative with curl:**
```bash
sudo bash -c "$(curl -sSL https://raw.githubusercontent.com/theredstonee/Techtyl/main/install.sh)"
```

The installer will:
1. ✅ Install all system dependencies (PHP 8.2, MariaDB, Nginx, Redis)
2. ✅ Download and configure Pterodactyl Panel
3. ✅ Install Techtyl AI features
4. ✅ Configure Azure OpenAI integration
5. ✅ Set up user registration and server creation
6. ✅ Create admin account
7. ✅ Start all services

### During Installation

You'll be prompted for:

**Server Configuration:**
- Panel URL (auto-detects your server IP)

**Azure OpenAI Configuration:**
- API Key
- Endpoint (e.g., `https://your-resource.openai.azure.com/`)
- Deployment Name (e.g., `gpt-4o`)

**Admin Account:**
- Email
- Username
- Password

### Azure OpenAI Setup

1. Go to: https://portal.azure.com/
2. Create "Azure OpenAI" resource
3. Deploy model: **gpt-4o** (recommended)
4. Copy API Key & Endpoint from "Keys and Endpoint" section
5. Copy Deployment Name from "Model deployments"

---

## 🎮 Usage

### For Users

**Register Account:**
1. Navigate to panel URL
2. Click "Register" (if enabled)
3. Fill in username, email, password
4. Login with credentials

**Create Server:**
1. Click "Create New Server"
2. Select game type (Egg)
3. Click **"✨ AI Recommendations"** - AI suggests optimal resources
4. Click **"🏷️ Suggest Names"** - AI generates creative names
5. Ask questions in **"💬 Chat"** field
6. Click "Create Server"

### For Admins

**Access Admin Panel:**
```
https://your-panel-url/admin
```

**Create Nodes:**
1. Admin → Nodes → Create New
2. Configure node settings
3. Add allocations (ports)

**Manage Users:**
1. Admin → Users
2. View/edit user limits
3. Suspend/delete accounts

**Monitor System:**
```bash
# View logs
tail -f /var/www/pterodactyl/storage/logs/laravel.log

# Check services
systemctl status nginx php8.2-fpm pteroq
```

---

## ⚙️ Configuration

### User Settings

Edit `/var/www/pterodactyl/.env`:

```env
# User Registration
REGISTRATION_ENABLED=true                    # Enable/disable registration

# User Server Creation
USER_SERVER_CREATION_ENABLED=true           # Allow users to create servers
USER_SERVER_LIMIT=3                          # Max servers per user
USER_SERVER_MEMORY_LIMIT=4096                # Max RAM per server (MB)
USER_SERVER_CPU_LIMIT=200                    # Max CPU per server (%)
USER_SERVER_DISK_LIMIT=10240                 # Max disk per server (MB)

# AI Configuration
TECHTYL_AI_ENABLED=true                      # Enable/disable AI
TECHTYL_MAX_REQUESTS=50                      # Max AI requests per user/day
TECHTYL_CACHE_RESPONSES=true                 # Cache AI responses
TECHTYL_CACHE_DURATION=1440                  # Cache duration (minutes)

# Azure OpenAI
AZURE_OPENAI_API_KEY=your-key-here
AZURE_OPENAI_ENDPOINT=https://your-endpoint.openai.azure.com/
AZURE_OPENAI_DEPLOYMENT=gpt-4o
AZURE_OPENAI_API_VERSION=2024-02-15-preview
```

After changes:
```bash
cd /var/www/pterodactyl
php artisan config:clear
```

---

## 💰 Pricing

### Azure OpenAI Costs (GPT-4o)

| Usage | Monthly Cost | Requests |
|-------|--------------|----------|
| Small (10 users) | ~$5 | 100/month |
| Medium (50 users) | ~$25 | 500/month |
| Large (200 users) | ~$100 | 2000/month |

**Cost Optimization:**
- ✅ Response caching (24 hours)
- ✅ Rate limiting per user
- ✅ Most cost-effective model (GPT-4o)

**Free Credits**: New Azure accounts often receive $5-10 free credit!

---

## 🔧 Development

### Project Structure

```
Techtyl/
├── install.sh                                # Main installer
├── addon/
│   ├── app/
│   │   ├── Services/
│   │   │   └── AzureOpenAIService.php       # AI integration
│   │   └── Http/Controllers/Api/Client/Techtyl/
│   │       └── AIController.php              # API endpoints
│   ├── resources/scripts/components/techtyl/
│   │   └── AIAssistant.tsx                   # React component
│   ├── routes/
│   │   └── techtyl.php                       # API routes
│   └── config/
│       └── techtyl.php                       # Configuration
└── docs/
    ├── QUICK_START.md
    └── DEPLOYMENT_READY.md
```

### API Endpoints

```
POST /api/client/techtyl/ai/suggestions
POST /api/client/techtyl/ai/names
POST /api/client/techtyl/ai/help
```

---

## 🐛 Troubleshooting

### Line Ending Error: `cho: command not found`

**Problem:** Scripts downloaded on Linux may have Windows line endings (CRLF instead of LF).

**Solution:**

```bash
# Download script
wget https://raw.githubusercontent.com/theredstonee/Techtyl/main/install.sh

# Fix line endings
dos2unix install.sh || sed -i 's/\r$//' install.sh

# Run script
sudo bash install.sh
```

**Or use one command:**

```bash
wget -qO- https://raw.githubusercontent.com/theredstonee/Techtyl/main/install.sh | dos2unix | sudo bash
```

### 500 Internal Server Error

```bash
cd /var/www/pterodactyl

# Check logs
tail -50 storage/logs/laravel.log

# Fix permissions
sudo chown -R www-data:www-data .
sudo chmod -R 755 storage bootstrap/cache

# Clear cache
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Restart services
systemctl restart php8.2-fpm nginx
```

### AI Features Not Working

```bash
# Verify Azure OpenAI credentials
cat .env | grep AZURE_OPENAI

# Should show 4 values:
# AZURE_OPENAI_API_KEY=...
# AZURE_OPENAI_ENDPOINT=...
# AZURE_OPENAI_DEPLOYMENT=...
# AZURE_OPENAI_API_VERSION=...

# Test connection
tail -f storage/logs/laravel.log
# Then try using AI features
```

### Panel Not Accessible

```bash
# Check services
systemctl status nginx php8.2-fpm

# Check Nginx config
nginx -t

# View error logs
tail -20 /var/log/nginx/error.log
```

### Database Issues

```bash
# Check database connection
mysql -u pterodactyl -p panel

# View credentials
cat /root/techtyl-info.txt
```

---

## 🔄 Updates & Fixes

### 🚨 Emergency Fix (500 Errors, Permission Issues)

**If you're experiencing 500 errors or permission problems:**

```bash
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/emergency-fix.sh | sudo bash
```

This fixes:
- ✅ **500 Internal Server Error**
- ✅ **APP_URL without http://** (causes broken links)
- ✅ **Permission issues** (www-data ownership)
- ✅ **Multiple footer displays**
- ✅ **Cache problems**

**What it does:**
1. Fixes APP_URL (adds http:// if missing)
2. Sets proper permissions (directories 755, files 644)
3. Sets www-data ownership
4. Rebuilds cache
5. Restarts services

### Update Existing Installation

```bash
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/update-techtyl.sh | sudo bash
```

Adds:
- User Registration
- Improved Design
- Footer Branding

### Clean Reinstall

```bash
# Backup first!
sudo cp /var/www/pterodactyl/.env /root/pterodactyl-env.backup

# Remove old installation
sudo rm -rf /var/www/pterodactyl
sudo mysql -e "DROP DATABASE IF EXISTS panel;"

# Run installer
sudo wget -O - https://raw.githubusercontent.com/theredstonee/Techtyl/main/install.sh | sudo bash
```

---

## 📊 System Requirements

### Minimum

- 2 CPU cores
- 4GB RAM
- 20GB disk space
- Ubuntu 22.04/24.04 or Debian 11/12

### Recommended

- 4+ CPU cores
- 8GB+ RAM
- 40GB+ SSD
- Domain with SSL certificate

---

## 📚 Documentation

- [Quick Start Guide](QUICK_START.md)
- [Deployment Checklist](DEPLOYMENT_READY.md)
- [Installation Guide](ADDON_INSTALL_GUIDE.md)
- [Security Policy](SECURITY.md)

---

## 🤝 Contributing

Contributions are welcome! 🎉

1. Fork the project
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

---

## 📄 License

MIT License - See [LICENSE](LICENSE)

**Based on Pterodactyl Panel (MIT License)**

---

## 🙏 Credits

- Built on [Pterodactyl Panel](https://pterodactyl.io)
- AI powered by [Azure OpenAI](https://azure.microsoft.com/en-us/products/ai-services/openai-service)
- Community project (not officially affiliated with Pterodactyl)

---

## 🌟 Show Your Support

If you like Techtyl, give us a ⭐ on GitHub!

---

## 📞 Support & Community

- **Issues**: [GitHub Issues](https://github.com/theredstonee/Techtyl/issues)
- **Discussions**: [GitHub Discussions](https://github.com/theredstonee/Techtyl/discussions)
- **Documentation**: [Read the Docs](https://github.com/theredstonee/Techtyl/tree/main/docs)

---

## 📋 FAQ

**Q: Is this an official Pterodactyl addon?**
A: No, Techtyl is a community project built on top of Pterodactyl.

**Q: Does it modify Pterodactyl's database?**
A: It uses Pterodactyl's existing database structure.

**Q: Can I use it with existing Pterodactyl installation?**
A: Techtyl is a complete installation including Pterodactyl, not an addon to existing installations.

**Q: What's the difference from standard Pterodactyl?**
A: Techtyl adds AI features, user registration, and user server creation capabilities.

**Q: Is it free?**
A: The software is free (MIT License), but you pay for Azure OpenAI usage (~$5-100/month depending on usage).

**Q: Which Azure OpenAI model should I use?**
A: We recommend GPT-4o for the best balance of cost and performance.

---

<div align="center">

**Built with ❤️ and 🤖 for the gaming community**

[Website](https://github.com/theredstonee/Techtyl) • [Docs](QUICK_START.md) • [Azure Setup Guide](https://docs.microsoft.com/azure/ai-services/openai/)

</div>
