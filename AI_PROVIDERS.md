# 🤖 KI-Provider für Techtyl

Techtyl unterstützt verschiedene KI-APIs für den AI-Assistenten.

## 🔵 Claude API (Standard)

**Provider**: Anthropic
**Website**: https://console.anthropic.com/

### Setup

1. Account erstellen auf: https://console.anthropic.com/
2. API Key generieren
3. In `.env` eintragen:
```env
CLAUDE_API_KEY=sk-ant-dein-key-hier
CLAUDE_MODEL=claude-3-5-sonnet-20241022
CLAUDE_MAX_TOKENS=4096
```

### Kosten
- **Claude 3.5 Sonnet**: $3/1M Input + $15/1M Output
- **Claude 3 Haiku**: $0.25/1M Input + $1.25/1M Output

### Features
- ✅ Hervorragende Code-Qualität
- ✅ Lange Context-Fenster
- ✅ Präzise technische Antworten
- ✅ Deutschsprachig verfügbar

---

## 🟢 OpenAI GPT (Alternative)

**Provider**: OpenAI
**Website**: https://platform.openai.com/

### Setup

1. Account erstellen auf: https://platform.openai.com/
2. API Key generieren
3. In `.env` eintragen:
```env
OPENAI_API_KEY=sk-proj-dein-key-hier
OPENAI_MODEL=gpt-4-turbo-preview
OPENAI_MAX_TOKENS=4096
AI_PROVIDER=openai
```

4. In `backend/config/services.php` hinzufügen:
```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4-turbo-preview'),
    'max_tokens' => env('OPENAI_MAX_TOKENS', 4096),
],
```

5. In `ServerController.php` ändern:
```php
// Von:
use App\Services\ClaudeAIService;
protected ClaudeAIService $aiService;
public function __construct(ClaudeAIService $aiService)

// Zu:
use App\Services\OpenAIService;
protected OpenAIService $aiService;
public function __construct(OpenAIService $aiService)
```

### Kosten
- **GPT-4 Turbo**: $10/1M Input + $30/1M Output
- **GPT-3.5 Turbo**: $0.50/1M Input + $1.50/1M Output

### Features
- ✅ Weit verbreitet
- ✅ Gute Dokumentation
- ✅ Viele Features
- ⚠️ Teurer als Claude

---

## 🟠 Google Gemini (Geplant)

**Provider**: Google
**Website**: https://ai.google.dev/

### Setup (Coming Soon)

```env
GOOGLE_API_KEY=dein-key-hier
GOOGLE_MODEL=gemini-pro
AI_PROVIDER=google
```

### Kosten
- **Gemini Pro**: $0.50/1M Input + $1.50/1M Output
- **Kostenlos**: Bis zu 60 Anfragen/Minute

### Features
- ✅ Günstig/Kostenlos
- ✅ Schnell
- ⚠️ Noch nicht implementiert

---

## 🔴 Lokale KI (Geplant)

**Provider**: Ollama / LM Studio
**Website**: https://ollama.ai/

### Vorteile
- ✅ Komplett kostenlos
- ✅ Keine externen API-Aufrufe
- ✅ Volle Datenkontrolle
- ⚠️ Benötigt gute Hardware
- ⚠️ Noch nicht implementiert

### Models
- Llama 2/3
- Mistral
- CodeLlama
- Und mehr...

---

## 📊 Vergleich

| Provider | Kosten (ca.) | Qualität | Geschwindigkeit | Setup |
|----------|-------------|----------|-----------------|-------|
| **Claude** | 💰💰 | ⭐⭐⭐⭐⭐ | ⚡⚡⚡⚡ | ✅ Einfach |
| **OpenAI** | 💰💰💰 | ⭐⭐⭐⭐⭐ | ⚡⚡⚡⚡ | ✅ Einfach |
| **Gemini** | 💰 | ⭐⭐⭐⭐ | ⚡⚡⚡⚡⚡ | 🔜 Bald |
| **Lokal** | 🆓 | ⭐⭐⭐ | ⚡⚡⚡ | 🔜 Bald |

---

## 💡 Empfehlung

### Für Entwicklung
**Claude API** (Standard)
- Bestes Preis-Leistungs-Verhältnis
- Hervorragende technische Antworten
- Gute Deutsch-Unterstützung

### Für Produktion (geringe Nutzung)
**Claude API** oder **Gemini**
- Beide günstig bei geringer Nutzung
- Gute Qualität

### Für Produktion (hohe Nutzung)
**Gemini** oder **Lokale KI**
- Geringere/keine API-Kosten
- Skalierbar

### Für Datenschutz
**Lokale KI** (wenn implementiert)
- Keine Daten verlassen das System
- DSGVO-konform

---

## 🔧 API-Provider wechseln

### 1. OpenAI verwenden

```bash
# .env bearbeiten
cd backend
nano .env
```

```env
# Claude deaktivieren
# CLAUDE_API_KEY=...

# OpenAI aktivieren
OPENAI_API_KEY=sk-proj-dein-key
AI_PROVIDER=openai
```

```bash
# Code anpassen
nano app/Http/Controllers/ServerController.php
# ClaudeAIService durch OpenAIService ersetzen
```

### 2. Zwischen Providern wechseln

Man könnte auch ein Factory-Pattern implementieren:

```php
// backend/app/Services/AIServiceFactory.php
class AIServiceFactory {
    public static function make(): AIServiceInterface {
        $provider = config('services.ai.provider', 'claude');

        return match($provider) {
            'openai' => app(OpenAIService::class),
            'google' => app(GoogleAIService::class),
            'claude' => app(ClaudeAIService::class),
            default => app(ClaudeAIService::class),
        };
    }
}
```

---

## 🆓 Kostenlose Alternativen

### Ohne KI-Features starten
Du kannst Techtyl auch ohne KI verwenden:

```env
# .env
AI_ENABLED=false
```

Die AI-Buttons werden dann ausgeblendet, aber alle anderen Features funktionieren!

### Free-Tier nutzen
Viele Provider bieten kostenloses Startguthaben:
- **OpenAI**: $5 für neue Accounts
- **Anthropic**: Oft $5-10 für neue Accounts
- **Google Gemini**: Kostenlos bis 60 req/min

---

## 📞 Fragen?

Siehe [QUICKSTART.md](QUICKSTART.md) für weitere Hilfe.
