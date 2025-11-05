# Security Policy

## Reporting Security Vulnerabilities

We take the security of Techtyl seriously. If you discover a security vulnerability, please report it responsibly.

### Contact

**Please DO NOT report security vulnerabilities publicly via GitHub Issues!**

Instead, use one of these methods:
- **Email:** security@techtyl.io (preferred)
- **GitHub:** [Private Security Advisory](https://github.com/theredstonee/Techtyl/security/advisories/new)

### What to Report

Please report any security issues including:
- Authentication/Authorization bypass
- XSS, CSRF, SQL Injection
- Remote Code Execution (RCE)
- Sensitive data exposure
- API abuse vulnerabilities
- Configuration issues leading to security risks

### Our Response Process

1. **Acknowledgment** - Within 48 hours
2. **Analysis** - Assessment of severity and impact
3. **Fix** - Development and testing
4. **Release** - Security patch release
5. **Credit** - Recognition in changelog (if desired)

## Security Features

### Authentication & Authorization
- ✅ Bcrypt password hashing
- ✅ CSRF protection (Laravel built-in)
- ✅ Session security
- ✅ Rate limiting

### Input Validation
- ✅ Server-side validation
- ✅ SQL injection protection (Eloquent ORM)
- ✅ XSS protection
- ✅ Request sanitization

### API Security
- ✅ Token-based authentication
- ✅ API rate limiting
- ✅ Input validation
- ✅ Secure Azure OpenAI integration

## Best Practices

### Never Commit

**NEVER** commit these to Git:
- ❌ API keys
- ❌ Passwords
- ❌ `.env` files with credentials
- ❌ Private keys
- ❌ Database dumps

### Always Use

**ALWAYS** follow these practices:
- ✅ Environment variables (`.env`)
- ✅ Strong passwords (min 12 characters)
- ✅ HTTPS/SSL in production
- ✅ Firewall configuration
- ✅ Regular updates

### Secure Credentials Storage

**Azure OpenAI Keys:**

```bash
# ✅ CORRECT: In .env (not in Git)
AZURE_OPENAI_API_KEY=your-key-here
AZURE_OPENAI_ENDPOINT=https://your-endpoint.openai.azure.com/

# ❌ WRONG: Hardcoded in files
$apiKey = 'abc123...';
```

**Server Configuration:**

```bash
# Secure .env file
cd /var/www/pterodactyl
sudo chmod 600 .env
sudo chown www-data:www-data .env
```

## API Key Leak Response

**If your API key is exposed:**

1. **IMMEDIATELY** regenerate in Azure Portal:
   - Go to: Keys and Endpoint
   - Click: Regenerate Key

2. **Update** `.env` with new key

3. **Verify** old key is revoked

4. **Review** git history for exposure

## Security Checklist

### Before Deployment
- [ ] `.env` not in Git
- [ ] `.gitignore` properly configured
- [ ] Strong database passwords
- [ ] API keys regenerated for production
- [ ] SSL/HTTPS enabled
- [ ] Firewall configured
- [ ] Unnecessary ports closed

### Regular Maintenance
- [ ] System updates: `sudo apt update && sudo apt upgrade`
- [ ] Dependency updates: `composer update`
- [ ] Monitor logs: `tail -f storage/logs/laravel.log`
- [ ] Backup verification
- [ ] Security patches applied

## Patch Policy

- **Critical vulnerabilities:** Fix within 24-48 hours
- **High severity:** Fix within 7 days
- **Medium severity:** Fix in next release
- **Low severity:** Scheduled maintenance

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.2.x   | ✅ Yes            |
| 1.1.x   | ✅ Yes            |
| 1.0.x   | ⚠️ Security only  |
| < 1.0   | ❌ No             |

## Security Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security](https://laravel.com/docs/security)
- [Azure Security Best Practices](https://learn.microsoft.com/en-us/azure/security/)
- [Pterodactyl Security](https://pterodactyl.io/community/security.html)

## Disclosure Policy

- We follow responsible disclosure practices
- We will credit researchers (if desired)
- Please allow reasonable time for fixes before public disclosure
- Coordinated disclosure is appreciated

## Hall of Fame

Thank you to everyone who has responsibly reported security issues!

---

**Last Updated:** January 2025
**Version:** 1.2.0
