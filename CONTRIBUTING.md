# Contributing to Techtyl

Thank you for considering contributing to Techtyl! 🎉

## How to Contribute

### Reporting Bugs

1. Check if the bug is already reported in [Issues](https://github.com/theredstonee/Techtyl/issues)
2. Create a new issue with:
   - Clear description
   - Steps to reproduce
   - Expected vs actual behavior
   - System information (OS, PHP version)
   - Relevant logs

### Suggesting Features

1. Create an issue with the `enhancement` label
2. Describe the feature and its benefits
3. Discuss with the community

### Code Contributions

#### 1. Fork & Clone

```bash
git clone https://github.com/yourusername/Techtyl.git
cd Techtyl
```

#### 2. Create Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/your-bug-fix
```

#### 3. Make Changes

- Follow the existing code style
- Test your changes thoroughly
- Add comments for complex logic

#### 4. Commit

```bash
git add .
git commit -m "feat: add feature description"
# or
git commit -m "fix: fix bug description"
```

**Commit Message Format:**
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation
- `refactor:` - Code refactoring
- `test:` - Tests
- `chore:` - Build/maintenance

#### 5. Push & Pull Request

```bash
git push origin feature/your-feature-name
```

Then create a Pull Request on GitHub.

## Code Style

### Bash Scripts

- Use shellcheck for validation
- Add comments for complex logic
- Follow existing formatting
- Test on Ubuntu 22.04 and 24.04

### PHP (for addons)

- PSR-12 coding standard
- Use type hints
- Add DocBlocks

### Documentation

- Keep it simple and clear
- Use proper English
- Add examples where helpful

## Testing

Test your changes on:
- ✅ Fresh Ubuntu 22.04
- ✅ Fresh Ubuntu 24.04
- ✅ Existing Pterodactyl installations

## Security

**NEVER commit:**
- API keys
- Passwords
- Private keys
- `.env` files

**Always:**
- Validate user input
- Use parameterized queries
- Sanitize output
- Follow OWASP guidelines

### Reporting Security Issues

Email: security@techtyl.io (do NOT create public issues)

## Pull Request Checklist

- [ ] Code follows project style
- [ ] Tested on Ubuntu 22.04/24.04
- [ ] Documentation updated
- [ ] No merge conflicts
- [ ] Commit messages are clear
- [ ] Security best practices followed

## Questions?

- [GitHub Discussions](https://github.com/theredstonee/Techtyl/discussions)
- [Issues](https://github.com/theredstonee/Techtyl/issues)

Thank you for your contributions! 🚀
