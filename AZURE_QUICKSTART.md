# 🚀 Azure OpenAI - Schnellstart (3 Minuten)

## Schritt 1: Azure OpenAI Credentials holen

### 1.1 Azure Portal öffnen
https://portal.azure.com/

### 1.2 Azure OpenAI Resource erstellen (falls noch nicht vorhanden)
1. "Create a resource" → Suche "Azure OpenAI"
2. **Region**: West Europe (DSGVO, niedrige Latenz)
3. **Name**: `techtyl-openai` (oder frei wählbar)
4. **Pricing**: Standard S0
5. **Create** → Warten auf Deployment

### 1.3 Keys kopieren
1. Gehe zu deiner Azure OpenAI Resource
2. Links: **"Keys and Endpoint"**
3. Kopiere:
   - ✅ **KEY 1**
   - ✅ **Endpoint** (z.B. `https://techtyl-openai.openai.azure.com/`)

### 1.4 Model deployen
1. Klicke **"Go to Azure OpenAI Studio"**
2. **"Deployments"** → **"Create new deployment"**
3. Einstellungen:
   - **Model**: `gpt-4o` ⭐ (empfohlen)
   - **Deployment name**: `gpt4o-deployment` (frei wählbar)
   - **Tokens per minute**: 10K-20K
4. **Create**

---

## Schritt 2: Credentials in Code eintragen

Öffne: **`backend/app/Services/AzureOpenAIService.php`**

```php
public function __construct()
{
    // ========================================
    // 🔷 HIER DEINE CREDENTIALS EINTRAGEN:
    // ========================================

    // 1. API Key von Azure Portal
    $this->apiKey = 'abc123dein-key-hier-einfuegen';

    // 2. Endpoint (MIT / am Ende!)
    $this->endpoint = 'https://techtyl-openai.openai.azure.com/';

    // 3. Deployment-Name (den du in Azure erstellt hast)
    $this->deployment = 'gpt4o-deployment';

    // 4. API Version (so lassen)
    $this->apiVersion = '2024-02-15-preview';
}
```

**Beispiel (ausgefüllt):**
```php
$this->apiKey = '1a2b3c4d5e6f7g8h9i0j1k2l3m4n5o6p';
$this->endpoint = 'https://meine-ki.openai.azure.com/';
$this->deployment = 'gpt4o-deployment';
$this->apiVersion = '2024-02-15-preview';
```

---

## Schritt 3: Service aktivieren

Öffne: **`backend/app/Http/Controllers/ServerController.php`**

```php
// Zeile 3-4: Ändere
use App\Services\ClaudeAIService;      // ← LÖSCHEN

// ZU:
use App\Services\AzureOpenAIService;   // ← NEU

// Zeile 10-11: Ändere
protected ClaudeAIService $aiService;  // ← LÖSCHEN

// ZU:
protected AzureOpenAIService $aiService; // ← NEU

// Zeile 13: Ändere
public function __construct(ClaudeAIService $aiService)  // ← LÖSCHEN

// ZU:
public function __construct(AzureOpenAIService $aiService) // ← NEU
```

**Komplettes Beispiel:**
```php
<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\AzureOpenAIService;  // ← GEÄNDERT
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServerController extends Controller
{
    protected AzureOpenAIService $aiService;  // ← GEÄNDERT

    public function __construct(AzureOpenAIService $aiService)  // ← GEÄNDERT
    {
        $this->aiService = $aiService;
    }

    // ... Rest bleibt gleich
}
```

---

## Schritt 4: Testen

```bash
# Cache leeren
cd backend
php artisan config:clear

# Starten
cd ..
start.bat
```

**Test:**
1. Öffne http://localhost:3000
2. Login/Register
3. "Server erstellen"
4. Klicke "AI-Empfehlungen" → **Sollte jetzt funktionieren!** ✅

---

## 📊 Welches GPT-Model verwenden?

| Model | Kosten | Geschwindigkeit | Qualität | Empfehlung |
|-------|--------|-----------------|----------|------------|
| **gpt-4o** | 💰 | ⚡⚡⚡⚡⚡ | ⭐⭐⭐⭐⭐ | ✅ **BESTE WAHL** |
| gpt-4-turbo | 💰💰💰 | ⚡⚡⚡⚡ | ⭐⭐⭐⭐⭐ | Gut aber teurer |
| gpt-35-turbo | 💰 | ⚡⚡⚡⚡⚡ | ⭐⭐⭐ | Zu schwach |

**💡 Empfehlung: `gpt-4o`**
- Perfekte Balance
- ~$0.005 pro AI-Anfrage
- Schnell und intelligent

---

## 💰 Kosten

Mit **gpt-4o**:
- 1 AI-Anfrage ≈ $0.0045
- 100 Anfragen ≈ $0.45
- 1000 Anfragen ≈ $4.50

**Sehr günstig für Entwicklung!** 🎉

---

## 🐛 Häufige Fehler

### "Unauthorized" (401)
❌ API Key falsch
✅ Lösung: Key aus Azure Portal neu kopieren

### "Resource not found" (404)
❌ Endpoint oder Deployment-Name falsch
✅ Lösung: Prüfe beide Werte in Azure

### "Rate limit exceeded" (429)
❌ Zu viele Anfragen
✅ Lösung: Tokens per minute in Azure erhöhen

### Timeout
❌ Region zu weit weg
✅ Lösung: West Europe wählen (für DE)

---

## ⚠️ Sicherheits-Hinweis

**API Key ist jetzt im Code!**
- ⚠️ NICHT auf GitHub pushen!
- ⚠️ Nicht mit anderen teilen!
- ✅ Nur für Entwicklung okay
- ✅ Für Produktion: .env nutzen

**Für Produktion** später .env verwenden:
```php
// In AzureOpenAIService.php wieder aktivieren:
$this->apiKey = config('services.azure_openai.api_key');
$this->endpoint = config('services.azure_openai.endpoint');
// etc.

// In .env:
AZURE_OPENAI_API_KEY=dein-key
```

---

## ✅ Fertig!

Jetzt hast du:
- ✅ Azure OpenAI integriert
- ✅ Credentials fest im Code
- ✅ gpt-4o als AI-Model
- ✅ Sofort einsatzbereit

**Viel Erfolg mit Techtyl! 🚀**
