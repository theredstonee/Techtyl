# 🔷 Azure OpenAI Setup für Techtyl

Anleitung zur Verwendung von **Azure OpenAI** statt Claude API.

## ✨ Vorteile von Azure OpenAI

- ✅ **Enterprise-Ready**: SLA, Support, Compliance
- ✅ **DSGVO-konform**: Daten bleiben in Europa
- ✅ **Bessere Kontrolle**: Rate Limits, Monitoring
- ✅ **Integration**: Einfach mit anderen Azure-Services
- ✅ **Keine Warteliste**: Sofort verfügbar mit Azure-Account

---

## 🚀 Schritt 1: Azure OpenAI Service erstellen

### 1.1 Azure Portal öffnen
https://portal.azure.com/

### 1.2 Azure OpenAI Resource erstellen

1. Klicke auf **"Create a resource"**
2. Suche nach **"Azure OpenAI"**
3. Klicke auf **"Create"**

**Einstellungen:**
- **Subscription**: Deine Azure Subscription
- **Resource Group**: Neue erstellen (z.B. "techtyl-rg")
- **Region**:
  - **West Europe** (DSGVO, niedrige Latenz für DE)
  - **Sweden Central** (günstig, DSGVO)
  - **East US** (günstig, aber USA)
- **Name**: `techtyl-openai` (muss global eindeutig sein)
- **Pricing Tier**: Standard S0

4. **Review + Create** → **Create**
5. Warte 2-3 Minuten auf Deployment

---

## 🔑 Schritt 2: API Key und Endpoint abrufen

1. Gehe zu deiner **Azure OpenAI Resource**
2. Im linken Menü: **"Keys and Endpoint"**
3. Kopiere:
   - ✅ **Key 1** (oder Key 2)
   - ✅ **Endpoint** (z.B. `https://techtyl-openai.openai.azure.com/`)

---

## 🤖 Schritt 3: Model Deployment erstellen

### 3.1 Azure OpenAI Studio öffnen

1. In deiner Resource klicke: **"Go to Azure OpenAI Studio"**
2. Oder direkt: https://oai.azure.com/

### 3.2 Model deployen

1. Gehe zu **"Deployments"**
2. Klicke **"Create new deployment"**

**Empfohlene Einstellungen:**

| Feld | Wert | Warum |
|------|------|-------|
| **Model** | `gpt-4o` | 🔥 **Beste Wahl**: Schnell, günstig, intelligent |
| | `gpt-4-turbo` | Gut, aber teurer |
| | `gpt-35-turbo` | Günstig, aber weniger intelligent |
| **Deployment name** | `gpt4o-deployment` | Frei wählbar (merken!) |
| **Content filter** | Default | Okay für meisten Fälle |
| **Tokens per minute** | 10K-80K | Je nach Bedarf |

3. Klicke **"Create"**

### 📊 Modell-Vergleich

| Modell | Preis (pro 1k Tokens) | Geschwindigkeit | Qualität | Empfehlung |
|--------|----------------------|-----------------|----------|------------|
| **gpt-4o** | ~$0.0025/$0.01 | ⚡⚡⚡⚡⚡ | ⭐⭐⭐⭐⭐ | ✅ **Best Choice** |
| gpt-4-turbo | ~$0.01/$0.03 | ⚡⚡⚡⚡ | ⭐⭐⭐⭐⭐ | Gut aber teurer |
| gpt-4 | ~$0.03/$0.06 | ⚡⚡⚡ | ⭐⭐⭐⭐⭐ | Zu teuer |
| gpt-35-turbo | ~$0.0005/$0.0015 | ⚡⚡⚡⚡⚡ | ⭐⭐⭐ | Zu schwach |

**💡 Meine Empfehlung: `gpt-4o`**
- Perfekte Balance: Schnell, günstig, intelligent
- Besser als gpt-3.5, günstiger als gpt-4
- Ideal für Techtyl!

---

## ⚙️ Schritt 4: Techtyl konfigurieren

### 4.1 Backend-Config anpassen

**`backend/config/services.php`** - Füge hinzu:

```php
'azure_openai' => [
    'api_key' => env('AZURE_OPENAI_API_KEY'),
    'endpoint' => env('AZURE_OPENAI_ENDPOINT'),
    'deployment' => env('AZURE_OPENAI_DEPLOYMENT'),
    'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-02-15-preview'),
],
```

### 4.2 .env konfigurieren

**`backend/.env`** - Ändere:

```env
# ========================================
# AZURE OPENAI - Statt Claude
# ========================================

# API Key von Azure Portal → Keys and Endpoint
AZURE_OPENAI_API_KEY=dein_azure_key_hier

# Endpoint von Azure Portal (mit / am Ende!)
AZURE_OPENAI_ENDPOINT=https://techtyl-openai.openai.azure.com/

# Dein Deployment-Name (von Azure OpenAI Studio)
AZURE_OPENAI_DEPLOYMENT=gpt4o-deployment

# API Version (neueste)
AZURE_OPENAI_API_VERSION=2024-02-15-preview

# Claude deaktivieren (optional)
# CLAUDE_API_KEY=
```

### 4.3 Service im Controller verwenden

**`backend/app/Http/Controllers/ServerController.php`** - Ändere:

```php
// VORHER:
use App\Services\ClaudeAIService;

class ServerController extends Controller
{
    protected ClaudeAIService $aiService;

    public function __construct(ClaudeAIService $aiService)
    {
        $this->aiService = $aiService;
    }
}

// NACHHER:
use App\Services\AzureOpenAIService;

class ServerController extends Controller
{
    protected AzureOpenAIService $aiService;

    public function __construct(AzureOpenAIService $aiService)
    {
        $this->aiService = $aiService;
    }
}
```

---

## 🧪 Schritt 5: Testen

```bash
# Cache leeren
cd backend
php artisan config:clear
php artisan cache:clear

# Server starten
php artisan serve
```

```bash
# Frontend starten
cd frontend
npm run dev
```

**Test:**
1. Öffne http://localhost:3000
2. Registriere dich / Login
3. Gehe zu "Server erstellen"
4. Klicke auf "AI-Empfehlungen" → Sollte funktionieren! ✅

---

## 💰 Kosten-Schätzung

### Mit **gpt-4o** (empfohlen):

| Nutzung | Kosten/Monat | Anfragen/Monat |
|---------|--------------|----------------|
| Entwicklung | ~$1-5 | 500-2000 |
| Kleine Prod | ~$10-20 | 5000-10000 |
| Mittlere Prod | ~$50-100 | 25000-50000 |

**Beispiel-Rechnung:**
- 1 AI-Anfrage ≈ 200 Input + 400 Output Tokens
- Kosten: (200 × $0.0025 + 400 × $0.01) / 1000 ≈ $0.0045
- 1000 Anfragen ≈ $4.50

**💡 Tipp**: Nutze Azure Cost Management für Monitoring!

---

## 🛡️ Best Practices

### Rate Limits setzen

In Azure OpenAI Studio → Deployments → Dein Deployment:
- **Tokens per minute**: 10K-20K für Start
- Kann später erhöht werden

### Content Filter

Standard-Filter ist okay, aber:
- Für Gaming-Content evtl. **"Medium"** statt "High"
- Verhindert false-positives bei Gaming-Begriffen

### Monitoring

Azure Portal → Deine Resource → Monitoring:
- **Überwache Kosten** (Cost Management)
- **Request Counts** (Metrics)
- **Error Rates** (Logs)

### Fehlerbehandlung

Der `AzureOpenAIService` hat bereits:
- ✅ Timeout (30s)
- ✅ Error Logging
- ✅ Exception Handling
- ✅ Fallback bei JSON-Parse-Fehlern

---

## 🆚 Azure vs Claude vs OpenAI

| Feature | Azure OpenAI | Claude | OpenAI direkt |
|---------|--------------|--------|---------------|
| **DSGVO** | ✅ EU-Region | ⚠️ USA | ⚠️ USA |
| **SLA** | ✅ 99.9% | ❌ Nein | ⚠️ Paid only |
| **Enterprise** | ✅ Ja | ❌ Nein | ⚠️ Limited |
| **Kosten** | 💰💰 Mittel | 💰 Günstig | 💰💰💰 Teuer |
| **Setup** | ⚙️⚙️ Mittel | ⚙️ Einfach | ⚙️ Einfach |
| **Qualität** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |

**Fazit**: Azure OpenAI ist perfekt für:
- 🏢 Business/Enterprise
- 🇪🇺 DSGVO-Compliance
- 📊 Monitoring & Control
- 🔧 Azure-Integration

---

## 🐛 Troubleshooting

### "Unauthorized" / 401 Error

```
❌ API request failed: 401
```

**Lösung:**
- Prüfe `AZURE_OPENAI_API_KEY` in .env
- Key muss aus Azure Portal kopiert sein
- Keine Anführungszeichen um den Key!

### "Resource not found" / 404 Error

```
❌ API request failed: 404
```

**Lösung:**
- Prüfe `AZURE_OPENAI_ENDPOINT` (muss mit / enden!)
- Prüfe `AZURE_OPENAI_DEPLOYMENT` (Name exakt wie in Azure)
- Deployment muss erstellt sein!

### "Rate limit exceeded" / 429 Error

```
❌ API request failed: 429
```

**Lösung:**
- Erhöhe Tokens per minute in Azure
- Warte kurz und probiere erneut
- Implementiere Retry-Logic (optional)

### Timeout-Fehler

```
❌ Timeout after 30 seconds
```

**Lösung:**
- Azure-Region näher wählen (West Europe)
- Deployment-Tokens erhöhen
- In Code: Timeout erhöhen (in `AzureOpenAIService.php`)

---

## 📚 Weitere Ressourcen

- **Azure OpenAI Docs**: https://learn.microsoft.com/en-us/azure/ai-services/openai/
- **Pricing Calculator**: https://azure.microsoft.com/en-us/pricing/calculator/
- **Quickstart Guide**: https://learn.microsoft.com/en-us/azure/ai-services/openai/quickstart

---

## ✅ Checkliste

- [ ] Azure OpenAI Resource erstellt
- [ ] API Key kopiert
- [ ] Endpoint kopiert
- [ ] Model deployt (gpt-4o empfohlen)
- [ ] `config/services.php` angepasst
- [ ] `.env` konfiguriert
- [ ] `ServerController.php` angepasst
- [ ] Cache geleert
- [ ] Getestet mit "Server erstellen"

**Fertig!** 🎉
