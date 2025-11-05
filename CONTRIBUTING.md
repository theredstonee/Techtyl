# Contributing zu Techtyl

Vielen Dank für dein Interesse, zu Techtyl beizutragen! 🎉

## 🤝 Wie kann ich beitragen?

### Fehler melden
- Prüfe zuerst, ob der Fehler bereits gemeldet wurde
- Erstelle ein Issue mit detaillierten Informationen:
  - Schritten zur Reproduktion
  - Erwartetes vs. tatsächliches Verhalten
  - Screenshots (falls relevant)
  - System-Informationen

### Features vorschlagen
- Erstelle ein Issue mit dem Label "feature request"
- Beschreibe das Feature und den Nutzen
- Diskutiere mit der Community

### Code beitragen

#### 1. Fork & Clone
```bash
git clone https://github.com/yourusername/techtyl.git
cd techtyl
```

#### 2. Branch erstellen
```bash
git checkout -b feature/mein-neues-feature
# oder
git checkout -b fix/mein-bugfix
```

#### 3. Code schreiben
- Folge den Code-Style-Guidelines (siehe unten)
- Schreibe Tests für neue Features
- Kommentiere komplexen Code

#### 4. Commit
```bash
git add .
git commit -m "feat: Beschreibung des Features"
# oder
git commit -m "fix: Beschreibung des Bugfixes"
```

**Commit-Message-Format:**
- `feat:` - Neues Feature
- `fix:` - Bugfix
- `docs:` - Dokumentation
- `style:` - Code-Formatierung
- `refactor:` - Code-Refactoring
- `test:` - Tests
- `chore:` - Build/Tools

#### 5. Push & Pull Request
```bash
git push origin feature/mein-neues-feature
```

Erstelle dann einen Pull Request auf GitHub.

## 📝 Code-Style-Guidelines

### PHP/Laravel
- PSR-12 Coding Standard
- Type Hints verwenden
- DocBlocks für Methoden
- Aussagekräftige Variablennamen

```php
<?php

/**
 * Get server configuration suggestions from AI
 *
 * @param string $gameType The type of game server
 * @param array $requirements Additional requirements
 * @return array Configuration suggestions
 */
public function getServerConfigSuggestions(string $gameType, array $requirements = []): array
{
    // Implementation
}
```

### TypeScript/React
- Funktionale Komponenten bevorzugen
- TypeScript-Types definieren
- Props-Interface für Komponenten
- Hooks korrekt verwenden

```typescript
interface ServerCardProps {
  server: Server;
  onDelete: (id: number) => void;
}

export function ServerCard({ server, onDelete }: ServerCardProps) {
  // Component implementation
}
```

### CSS/Tailwind
- Utility-First-Ansatz
- Wiederverwendbare Komponenten-Klassen
- Responsive Design (mobile-first)

```tsx
<div className="card hover:shadow-md transition-shadow">
  <h3 className="text-lg font-semibold text-gray-900">
    {server.name}
  </h3>
</div>
```

## 🧪 Testing

### Backend Tests
```bash
cd backend
php artisan test
```

### Frontend Tests
```bash
cd frontend
npm run test
```

## 🔒 Sicherheit

- **NIEMALS** API-Keys committen
- XSS-Protection beachten
- SQL-Injection vermeiden (Eloquent verwenden)
- Input-Validierung immer durchführen
- Security Headers setzen

### Sicherheitslücken melden
Sende eine E-Mail an security@techtyl.io (keine öffentlichen Issues!)

## 📚 Dokumentation

- Neue Features dokumentieren (FEATURES.md)
- Setup-Schritte aktualisieren (SETUP.md)
- Code-Kommentare auf Englisch
- User-facing Texte auf Deutsch

## 🎯 Prioritäten

### High Priority
- Security Fixes
- Critical Bugs
- Performance-Probleme

### Medium Priority
- Feature Requests mit vielen Votes
- UI/UX-Verbesserungen
- Dokumentation

### Low Priority
- Nice-to-have Features
- Code-Refactoring
- Style-Anpassungen

## ✅ Pull Request Checklist

- [ ] Code folgt den Style-Guidelines
- [ ] Tests wurden geschrieben/angepasst
- [ ] Alle Tests laufen durch
- [ ] Dokumentation wurde aktualisiert
- [ ] Commit-Messages sind aussagekräftig
- [ ] Keine Merge-Konflikte
- [ ] XSS-Protection beachtet
- [ ] Input-Validierung implementiert

## 🌟 Anerkennung

Alle Contributors werden in der README.md erwähnt!

## 📞 Fragen?

- GitHub Discussions
- Discord: [Link zum Discord]
- E-Mail: contribute@techtyl.io

Danke für deine Unterstützung! 🚀
