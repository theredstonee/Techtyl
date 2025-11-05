# Techtyl - Projekt-Übersicht

## 📁 Projektstruktur

```
Techtyl/
├── backend/                          # Laravel Backend
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── AuthController.php       # Authentifizierung (Login, Register)
│   │   │   │   └── ServerController.php     # Server-Management + AI
│   │   │   └── Middleware/
│   │   │       └── XssProtection.php        # XSS-Schutz Middleware
│   │   ├── Models/
│   │   │   ├── User.php                     # Benutzer-Model
│   │   │   ├── Server.php                   # Server-Model
│   │   │   └── Allocation.php               # Port-Allocations
│   │   └── Services/
│   │       └── ClaudeAIService.php          # Claude AI Integration
│   ├── config/
│   │   ├── services.php                     # Claude API Config
│   │   └── techtyl.php                      # Techtyl Settings
│   ├── database/
│   │   └── migrations/                      # Datenbank-Struktur
│   ├── routes/
│   │   └── api.php                          # API-Routen
│   ├── .env.example                         # Environment-Template
│   ├── composer.json                        # PHP-Dependencies
│   └── Dockerfile                           # Docker-Image für Backend
│
├── frontend/                         # React Frontend
│   ├── src/
│   │   ├── components/                      # (zukünftig)
│   │   ├── pages/
│   │   │   ├── Login.tsx                    # Login-Seite
│   │   │   ├── Register.tsx                 # Registrierungs-Seite
│   │   │   ├── Dashboard.tsx                # Haupt-Dashboard
│   │   │   └── CreateServer.tsx             # Server-Erstellung mit AI
│   │   ├── store/
│   │   │   ├── authStore.ts                 # Auth-State-Management
│   │   │   ├── serverStore.ts               # Server-State
│   │   │   └── aiStore.ts                   # AI-Funktionen
│   │   ├── lib/
│   │   │   └── api.ts                       # Axios API-Client
│   │   ├── utils/
│   │   │   └── sanitize.ts                  # XSS-Protection (Frontend)
│   │   ├── App.tsx                          # Haupt-App-Komponente
│   │   ├── main.tsx                         # Entry-Point
│   │   └── index.css                        # Tailwind CSS
│   ├── package.json                         # NPM-Dependencies
│   ├── tailwind.config.js                   # Tailwind-Config
│   ├── vite.config.ts                       # Vite-Build-Config
│   ├── Dockerfile                           # Docker-Image für Frontend
│   └── index.html                           # HTML-Template
│
├── scripts/                          # (zukünftig)
│
├── docs/                             # (zukünftig)
│
├── .gitignore                        # Git-Ignore-File
├── README.md                         # Projekt-Readme
├── SETUP.md                          # Setup-Anleitung
├── FEATURES.md                       # Feature-Liste
├── CONTRIBUTING.md                   # Contribution-Guidelines
├── PROJECT_OVERVIEW.md               # Diese Datei
├── docker-compose.yml                # Docker-Compose-Setup
├── install.sh                        # One-Click-Installation
└── package.json                      # Root-Package

```

## 🔑 Kern-Komponenten

### Backend (Laravel)

#### 1. AuthController
**Zweck**: Benutzer-Authentifizierung
- `register()` - Neue Benutzer registrieren
- `login()` - Benutzer anmelden
- `logout()` - Benutzer abmelden
- `me()` - Aktuellen Benutzer abrufen

**Features**:
- Password-Hashing (Bcrypt)
- E-Mail-Verifizierung (optional)
- Server-Limit pro Benutzer
- Input-Validierung mit Regex

#### 2. ServerController
**Zweck**: Server-Management mit AI-Integration
- `index()` - Alle Server des Benutzers
- `store()` - Neuen Server erstellen
- `show()` - Server-Details
- `update()` - Server bearbeiten
- `destroy()` - Server löschen
- `control()` - Server starten/stoppen
- `resources()` - Ressourcen-Auslastung
- `getAISuggestions()` - AI-Konfigurationsempfehlungen
- `getAIHelp()` - AI-Hilfe zu Fragen
- `getNameSuggestions()` - AI-Namen-Vorschläge
- `getTroubleshootHelp()` - AI-Problemlösung

#### 3. ClaudeAIService
**Zweck**: Integration mit Claude AI
- `helpWithServerCreation()` - Unterstützung bei Server-Erstellung
- `getServerConfigSuggestions()` - Ressourcen-Empfehlungen
- `helpTroubleshoot()` - Fehlerbehebung
- `suggestServerNames()` - Namen-Generierung
- `sendMessage()` - Kommunikation mit Claude API

**API-Endpunkt**: https://api.anthropic.com/v1/messages

#### 4. XssProtection Middleware
**Zweck**: XSS-Angriffe verhindern
- Input-Sanitisierung
- Security-Headers setzen
- Script-Tag-Entfernung
- HTML-Entity-Encoding

### Frontend (React + TypeScript)

#### 1. State Management (Zustand)

**authStore.ts**
```typescript
- user: User | null
- token: string | null
- login(email, password)
- register(name, email, password, confirmation)
- logout()
- fetchUser()
```

**serverStore.ts**
```typescript
- servers: Server[]
- fetchServers()
- createServer(data)
- deleteServer(id)
- controlServer(id, action)
```

**aiStore.ts**
```typescript
- getSuggestions(gameType, players)
- getHelp(question, context)
- getNameSuggestions(gameType, count)
- troubleshoot(serverId, issue)
```

#### 2. Pages

**Login.tsx / Register.tsx**
- Authentifizierungs-Forms
- Input-Validierung
- Error-Handling
- Responsive Design

**Dashboard.tsx**
- Server-Übersicht
- Ressourcen-Anzeige (CPU/RAM/Disk)
- Server-Kontrollen (Start/Stop/Delete)
- Status-Badges
- Server-Limit-Anzeige

**CreateServer.tsx**
- Server-Konfigurations-Form
- AI-Assistent-Sidebar
- Live-Empfehlungen
- Namen-Vorschläge
- Ressourcen-Slider
- Interactive Help

#### 3. Utilities

**sanitize.ts**
- DOMPurify-Integration
- XSS-Protection
- HTML-Escaping
- Input-Cleaning

**api.ts**
- Axios-Client
- Request/Response-Interceptors
- Token-Management
- Auto-Sanitization
- Error-Handling

## 🗄️ Datenbank-Schema

### users
```sql
- id (PK)
- name
- email (unique)
- email_verified_at
- password (hashed)
- server_limit (default: 3)
- is_admin (default: false)
- remember_token
- created_at
- updated_at
```

### servers
```sql
- id (PK)
- user_id (FK -> users.id)
- name
- description (nullable)
- game_type
- cpu (integer)
- memory (integer, MB)
- disk (integer, MB)
- status (enum: running, stopped, installing)
- created_at
- updated_at
- deleted_at (soft delete)
```

### allocations
```sql
- id (PK)
- server_id (FK -> servers.id)
- ip
- port
- is_primary (boolean)
- created_at
- updated_at
- UNIQUE(ip, port)
```

### personal_access_tokens (Laravel Sanctum)
```sql
- id (PK)
- tokenable_type
- tokenable_id
- name
- token (unique)
- abilities
- last_used_at
- expires_at
- created_at
- updated_at
```

## 🔄 API-Endpunkte

### Public
```
POST   /api/register        - Registrierung
POST   /api/login           - Login
```

### Protected (Auth Required)
```
POST   /api/logout          - Logout
GET    /api/me              - Aktueller Benutzer

GET    /api/servers         - Alle Server
POST   /api/servers         - Server erstellen
GET    /api/servers/{id}    - Server-Details
PATCH  /api/servers/{id}    - Server aktualisieren
DELETE /api/servers/{id}    - Server löschen

POST   /api/servers/{id}/control   - Server steuern (start/stop/restart)
GET    /api/servers/{id}/resources - Ressourcen-Auslastung

POST   /api/ai/suggestions         - AI-Konfigurations-Empfehlungen
POST   /api/ai/help                - AI-Hilfe
POST   /api/ai/name-suggestions    - Namen-Vorschläge
POST   /api/servers/{id}/ai/troubleshoot - Troubleshooting
```

## 🔐 Sicherheits-Schichten

### Layer 1: Frontend
- DOMPurify Input-Sanitization
- Content Security Policy (CSP)
- Input-Validierung (regex, type checking)
- HTTPS-only Cookies

### Layer 2: Network
- HTTPS-Verschlüsselung
- Security Headers (X-Frame-Options, X-XSS-Protection, etc.)
- CORS-Policy

### Layer 3: Backend
- XSS-Protection Middleware
- CSRF-Token-Validierung
- SQL-Injection-Schutz (Eloquent ORM)
- Password-Hashing (Bcrypt)
- Rate Limiting

### Layer 4: Database
- Prepared Statements
- Foreign Key Constraints
- Unique Constraints
- Index-Optimierung

## 🚀 Deployment-Flow

### Entwicklung
```bash
# Backend
cd backend && php artisan serve

# Frontend
cd frontend && npm run dev
```

### Docker
```bash
docker-compose up -d
```

### Produktion
```bash
# Build Frontend
cd frontend && npm run build

# Optimize Backend
cd backend
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Deploy mit Nginx
```

## 📊 Performance-Optimierungen

### Backend
- Laravel Config/Route/View Caching
- Redis für Sessions & Cache
- Database Query Optimization
- Eager Loading (N+1 vermeiden)

### Frontend
- Vite Code-Splitting
- Lazy Loading
- Image Optimization
- Tailwind CSS Purge

### Database
- Indexed Columns (email, user_id, etc.)
- Query Optimization
- Connection Pooling

## 🧪 Testing-Strategie

### Unit Tests
- Model Tests
- Service Tests
- Utility Tests

### Integration Tests
- API-Endpoint Tests
- Auth-Flow Tests
- Server-CRUD Tests

### E2E Tests
- User Registration Flow
- Server Creation Flow
- AI-Assistant Interaction

## 📈 Monitoring & Logging

### Backend Logs
- `storage/logs/laravel.log`
- API-Request Logs
- Error Logs
- AI-Service Logs

### Frontend Logs
- Browser Console
- Error Boundary
- Analytics (zukünftig)

## 🔮 Next Steps

1. **Wings-Daemon-Integration**: Echte Container-Verwaltung
2. **Multi-Node-Support**: Verteilte Server
3. **Backup-System**: Automatische Backups
4. **Billing-Integration**: Stripe/PayPal
5. **Mobile App**: React Native

## 📝 Wichtige Dateien

| Datei | Zweck |
|-------|-------|
| `backend/.env` | Backend-Konfiguration (API-Keys, DB) |
| `backend/routes/api.php` | API-Routen-Definition |
| `frontend/src/App.tsx` | React-Router-Setup |
| `docker-compose.yml` | Docker-Services |
| `install.sh` | Automatische Installation |

## 🤝 Contribution

Siehe [CONTRIBUTING.md](CONTRIBUTING.md) für Details.

## 📄 Lizenz

MIT License - Siehe LICENSE
