# 🛠️ AI Agent Instructions for SOCIMOB Codebase

## Project Overview
**SOCIMOB**: Multi-tenant real estate SaaS platform. Stack: **Lumen 10** (PHP backend API) + **React 19** (frontend) + **MySQL**

### Key Architecture
- **Backend**: Lumen 10 (lightweight Laravel), PHP 8.1+, JSON API only
- **Frontend**: React 19 + TypeScript + Vite (in `client/` → builds to `dist/public/`)
- **Multi-tenancy**: Via `BelongsToTenant` trait (auto-injects `tenant_id` on all queries)
- **Auth**: Custom base64 token format `user_id|timestamp|secret` (not JWT)
- **Critical**: `router.php` enables PHP built-in server for unified backend+frontend delivery
- **Deployment**: Vite build copies to `public/`, then entire project deployed via SSH/FTP

## 🚀 Quick Start (Local Development)

### Prerequisites
- PHP 8.1+ (extensions: mysqli, pdo_mysql, mbstring, openssl)
- Node.js 20+ and pnpm
- MySQL 5.7+
- Database `exclusiva` (auto-created if missing)

### Startup
```bash
# Terminal 1: Backend (root)
START.bat               # Windows
# or: php -S 127.0.0.1:8000 -t public router.php

# Terminal 2: Frontend React (in client/)
cd client && pnpm dev   # Dev server + HMR at http://localhost:3000
```

### URLs
- **Admin/CRM**: http://127.0.0.1:8000/app/ (legacy HTML/jQuery, being replaced by React)
- **Client Portal**: http://127.0.0.1:8000/portal/
- **API**: http://127.0.0.1:8000/api/
- **React Dev**: http://localhost:3000/ (dev-only, proxies API to port 8000)

### Database
```bash
php artisan migrate          # Run migrations (auto-runs if DB empty)
php check_db.php             # Verify connection
php create_superadmin.php    # Create test admin
```

### Credentials
- **Super Admin**: `admin@exclusiva.com` / `password`
- Test scripts in root: `check_*.php`, `test_*.php`, `create_*.php`

## 🏗️ Architecture & Patterns

### Multi-Tenancy (CRITICAL)
Data isolation via `tenant_id` on all tables. Every query automatically filtered by current tenant.

**BelongsToTenant Trait** (`app/Models/Traits/BelongsToTenant.php`):
```php
use App\Models\Traits\BelongsToTenant;
class Lead extends Model {
    use BelongsToTenant;  // Auto-filters queries, injects tenant_id on create
}
```
- **Global Scope**: Automatically filters all queries by `tenant_id`
- **Boot**: Injects `tenant_id` in `creating()` hook
- **Bypass**: `Model::withoutTenant()->get()` for admin queries
- **Scope**: `Model::forTenant($id)` for explicit tenant filtering

**ResolveTenant Middleware** (`app/Http/Middleware/ResolveTenant.php`):
- Resolves tenant by domain/subdomain or ngrok (dev)
- Injects `app('tenant')` and `$request->attributes['tenant_id']`
- On localhost/ngrok: uses first tenant in database

### Authentication
**SimpleTokenAuth** (`app/Http/Middleware/SimpleTokenAuth.php`):
- Format: base64(`user_id|timestamp|secret`)
- Header: `Authorization: Bearer <token>`
- Injects: `$request->user()` and `app('tenant')`
- No JWT/sessions—stateless token verification only

**User Roles** (in `users.role`):
- `super_admin` → Access all tenants, no isolation
- `admin` → Imobiliária admin
- `corretor` → Property agent
- `cliente` → Client portal user

### Routes Structure
```
routes/
├── web.php           # Public (auth, webhooks, health)
├── admin.php         # Leads, properties, conversations
├── client-portal.php # Client API
├── super-admin.php   # Tenant management
├── domains.php       # Custom domains
├── portal.php        # Client pages
├── subscriptions.php # Billing
└── themes.php        # Customizable themes
```

**Key Pattern**: All route files loaded automatically by `bootstrap/app.php`. Public routes (webhooks) in `web.php`.

```php
// Protected routes
$router->group(['middleware' => 'simple-auth'], function () use ($router) {
    $router->get('/api/leads', 'LeadsController@index');
});

// Super Admin (bypasses tenant validation)
$router->group(['prefix' => 'api/super-admin', 'middleware' => 'simple-auth'], ...);
```

### Backend Structure
**Location**: Project root (no `/backend` subfolder)

**Key Directories**:
- `app/Http/Controllers/` → Controllers (flat namespace, no nesting)
- `app/Services/` → Business logic (24 services including AI automation)
- `app/Models/Traits/` → Reusable model behaviors
- `app/Observers/` → Event listeners (e.g., LeadObserver for auto-IA startup)
- `app/Http/Middleware/` → Auth, tenant resolution, rate limiting

**Controller Pattern**:
- Place in `app/Http/Controllers/` (no nested namespaces)
- Extract logic to Services for reusability
- Always return JSON: `response()->json(['data' => $result])`

**Services** (24 in `app/Services/`):
- Injected via DI: `app(OpenAIService::class)->method()`
- Configured via `.env` (e.g., `EXCLUSIVA_OPENAI_API_KEY`)
- Key services: `LeadAutomationService`, `OpenAIService`, `TwilioService`, `TenantService`, `ImportacaoImoveisService`

**Observers**:
- `LeadObserver@created` → Auto-initiates IA attendance for Chaves na Mão leads
- Registered in `bootstrap/app.php`: `App\Models\Lead::observe(...)`

### External Integrations

**Twilio WhatsApp** (`app/Services/TwilioService.php`):
- Sends WhatsApp messages via Twilio
- Config: `EXCLUSIVA_TWILIO_ACCOUNT_SID`, `EXCLUSIVA_TWILIO_AUTH_TOKEN`, `EXCLUSIVA_TWILIO_WHATSAPP_FROM`
- Usage: `sendMessage($to, $body)` where `$to = 'whatsapp:+5531999999999'`

**OpenAI** (`app/Services/OpenAIService.php`):
- Whisper API for WhatsApp audio transcription
- GPT-4o-mini for response generation
- Config: `EXCLUSIVA_OPENAI_API_KEY`, `EXCLUSIVA_OPENAI_MODEL`
- Methods: `transcribeAudio($path)`, `chatCompletion($system, $user)`

**Chaves na Mão Webhook** (`POST /webhook/chaves-na-mao`):
- Receives leads from external source
- `LeadObserver` detects origin and auto-initiates IA via `LeadAutomationService`
- Manual trigger: `POST /api/admin/leads/{id}/iniciar-atendimento`

**IA Automation Flow**:
1. Lead created (webhook or manual)
2. Observer detects origin (Chaves na Mão = auto-trigger)
3. `LeadAutomationService` validates WhatsApp (Brazilian format)
4. Creates/reuses `Conversa` record
5. OpenAI generates personalized greeting (lead context)
6. Twilio sends via WhatsApp
7. Message logged, status updated

## 🎨 Frontend Architecture

### React Frontend (New - Primary)
**Location**: `client/` → builds to `dist/public/` → copied to `public/` on deploy

**Stack**:
- React 19 + TypeScript
- Vite (dev server + HMR)
- TailwindCSS v4
- Radix UI components
- React Router v6
- Axios for API calls

**Development**:
```bash
cd client
pnpm install          # Install deps
pnpm dev             # Dev server at http://localhost:3000
pnpm build           # Production build → dist/public/
```

**Key Pattern**: Always use relative paths in API calls (never hardcode `localhost:3000`):
```javascript
// Correct (relative)
fetch('/api/leads', {
  headers: { 'Authorization': `Bearer ${token}` }
});
```

**Vite Config**: Proxies `/api` → backend on port 8000 (dev only)

### HTML/jQuery Frontend (Legacy - Being Replaced)
**Location**: `public/app/` - served by same PHP server

**Structure**:
```
public/app/
├── login.html              # Unified login
├── dashboard.html          # Dashboard
├── leads.html              # Lead management + 🤖 IA button
├── imoveis.html            # Properties
├── conversas.html          # Chat (WhatsApp-style)
└── configuracoes.html      # Settings
```

**UI Patterns**:
- TailwindCSS via CDN
- jQuery 3.7.1 for AJAX
- `glow-*` classes for neon styling
- Feedback: `<div class="glow-feedback">` for success/error

**Login Flow**:
1. Frontend submits credentials to `/api/auth/login`
2. Backend returns token + user object with `role`
3. Frontend redirects by role:
   - `super_admin` → `/app/dashboard.html`
   - `admin`/`corretor` → `/app/dashboard.html`
   - `cliente` → `/portal/`
4. Token stored in `localStorage`

## 🚀 Development Workflows

### Backend Development
```bash
# Start server
php -S 127.0.0.1:8000 -t public router.php

# Debug via helper scripts (100+ in root)
php check_db.php              # Connection + schema
php check_tenant.php          # Current tenant
php check_users_roles.php     # List users
php test_login.php            # Auth flow
php test_api_simples.php      # Basic endpoints

# View logs
tail -f storage/logs/lumen-*.log
```

### Frontend Development
```bash
# In client/ directory
cd client

# Dev with hot reload
pnpm dev

# Production build
pnpm build

# After build, copy to public/
cp -r dist/public/* ../public/
```

### Database Changes
```bash
# Create migration
php artisan make:migration create_something

# Run all pending
php artisan migrate

# Rollback last batch
php artisan migrate:rollback
```

### Common Issues & Solutions

**Backend not responding**:
1. Check MySQL: `Get-Service mysql` (should be Running)
2. Create DB if missing: `mysql -u root -e "CREATE DATABASE exclusiva"`
3. Verify `.env`: `DB_CONNECTION=mysql`, `DB_DATABASE=exclusiva`
4. Check logs: `storage/logs/lumen-*.log`
5. Restart: Kill PHP process, restart with `START.bat`

**Frontend issues**:
1. Check backend: `http://127.0.0.1:8000/api/health` (should return JSON)
2. Clear localStorage: `localStorage.clear()` (F12 Console)
3. Check file exists: `public/app/*.html` present?
4. Vite dev server: Ensure running on port 3000

**Authentication problems**:
- Token format: base64(`user_id|timestamp|secret`)
- Header: `Authorization: Bearer <token>`
- Backend validates in `SimpleTokenAuth` middleware
- No sessions—stateless token verification

**Tenant issues**:
- Default to first tenant in DB (localhost/ngrok dev)
- Resolved by domain/subdomain in production
- Verify with: `php check_tenant.php`
- Override with: `Model::withoutTenant()->get()`