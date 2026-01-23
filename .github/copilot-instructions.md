# 🛠️ Instruções para Agentes de Codificação AI

## Visão Geral do Projeto
**SOCIMOB/Exclusiva**: Plataforma SaaS multi-tenant para gestão imobiliária. Stack: **Lumen 10** (backend API) + **React 19** (frontend novo) + **MySQL** (database).

### Arquitetura-Chave
- **Backend API**: Lumen 10 em PHP 8.1+, servindo JSON API
- **Frontend Principal**: React 19 + TypeScript + Vite (pasta `client/`)
- **Frontend Legado**: HTML/jQuery em `public/app/` (admin CRM antigo)
- **Build Process**: Vite gera build em `dist/public/` → copiado para `public/` no deploy
- **Lumen, não Laravel**: Sem facades complexos, rotas diretas em `routes/web.php`
- **Multi-tenancy via trait**: `BelongsToTenant` auto-injeta `tenant_id` em models
- **Token auth simples**: base64 encoding `user_id|timestamp|secret` (não JWT)

## 🚀 Início Rápido (Desenvolvimento Local)

### Pré-requisitos
- PHP 8.1+ (com extensões: mysqli, pdo_mysql, mbstring, openssl)
- Node.js 20+ e pnpm (para frontend React)
- MySQL rodando (XAMPP ou standalone)
- Banco `exclusiva` criado (ou será auto-criado)

### Execução (Desenvolvimento Local)

**Backend (API Lumen):**
```bash
# Windows (raiz do projeto)
START.bat

# Ou manual
php -S 127.0.0.1:8000 -t public router.php
```

**Frontend React (dev mode com HMR):**
```bash
# Terminal separado
cd client
pnpmFrontend React (dev)**: `http://localhost:3000/`
- **Backend API**: `http://127.0.0.1:8000/api/`
- **Admin CRM Legado**: `http://127.0.0.1:8000/app/` (HTML/jQuery)
```

**IMPORTANTE**: 
- O `router.php` é CRÍTICO para o servidor único funcionar em produção
- Em dev, use frontend React em porta 3000 (proxy para API na 8000)
- Vite build gera arquivos em `dist/public/` que são copiados para `public/` no deploy

### Acesso
- **Admin/CRM**: `http://127.0.0.1:8000/app/`
- **Portal Cliente**: `http://127.0.0.1:8000/portal/`
- **API Health**: `http://127.0.0.1:8000/api/health`

### Credenciais
- **Super Admin**: `admin@exclusiva.com` / `password`
- **Criar novos**: Veja scripts na raiz (`create_superadmin.php`, `quick_create_user.php`)

### Database Setup
```bash
# Migrations (auto-executadas se banco estiver vazio)
php artisan migrate

# Verificar estrutura/dados
php check_db.php           # Verifica conexão e tabelas
php check_tenant.php       # Verifica tenant atual
php check_users_roles.php  # Lista usuários e roles
```

**Helper Scripts**: A raiz contém 100+ scripts PHP para debug/diagnóstico:
- `check_*.php` → Diagnóstico de sistema/tenant/usuários
- `create_*.php` → Criar usuários/leads de teste
- `test_*.php` → Testar API/integrações/webhooks
- `fix_*.php` → Correções de dados/schema

## 🏗️ Arquitetura e Padrões

### Multi-Tenancy (CRÍTICO)
O isolamento de dados é feito via `tenant_id` em todas as tabelas:

**Trait BelongsToTenant** (`app/Models/Traits/BelongsToTenant.php`):
```php
// Em qualquer model que precisa isolamento:
use App\Models\Traits\BelongsToTenant;

class Lead extends Model {
    use BelongsToTenant; // Auto-filtra queries e adiciona tenant_id ao criar
}
```

**Middleware ResolveTenant** (`app/Http/Middleware/ResolveTenant.php`):
- Resolve tenant por domínio/subdomínio ou ngrok (dev)
- Injeta `app('tenant')` e `$request->attributes['tenant_id']`
- Em localhost/ngrok usa primeiro tenant do banco

**Global Scopes**: O trait adiciona automaticamente:
- `creating()`: Injeta `tenant_id` ao criar records
- `global scope 'tenant'`: Filtra todas as queries pelo tenant atual
- Para queries sem filtro: `Model::withoutTenant()->get()`

### Autenticação
**SimpleTokenAuth** (`app/Http/Middleware/SimpleTokenAuth.php`):
```php
// Formato: base64("user_id|timestamp|secret")
// Header: Authorization: Bearer <token>

// Middleware injeta:
// - $request->user() → User model
// - app('tenant') → Tenant do usuário
```

**Tipos de usuário** (campo `role` em `users`):
- `super_admin` → Acessa tudo, sem tenant_id
- `admin` → Admin da imobiliária (tenant)
- `corretor` → Corretor da imobiliária
- `cliente` → Cliente do portal

### Estrutura de Rotas
```
routes/
├── web.php           # Rotas principais (auth, portal público, webhooks)
├── super-admin.php   # /api/super-admin/* (gestão de tenants)
├── admin.php         # Rotas administrativas (leads, imóveis, conversas)
├── client-portal.php # Rotas do portal cliente (API)
├── portal.php        # Portal do cliente (pages)
├── domains.php       # Gestão de domínios customizados
├── subscriptions.php # Gestão de assinaturas
└── themes.php        # Temas personalizáveis
```

**IMPORTANTE**: Todos os arquivos em `routes/` são carregados automaticamente pelo `bootstrap/app.php`. Rotas públicas (webhooks, login) devem estar em `web.php`.

**Padrão de proteção**:
```php
// Rotas protegidas
$router->group(['middleware' => 'simple-auth'], function () use ($router) {
    $router->get('/api/leads', 'LeadsController@index');
});

// Super Admin (sem validação de tenant)
$router->group(['prefix' => 'api/super-admin', 'middleware' => 'simple-auth'], ...);
```

### Backend (Lumen)
**Localização**: Raiz do projeto (não há subpasta `backend/`)

**Estrutura**:
```
app/
├── Http/
│   ├── Controllers/   # Controllers organizados por feature
│   └── Middleware/    # SimpleTokenAuth, ResolveTenant, ValidateTenantAuth
├── Services/          # Lógica de negócio isolada
│   ├── TenantService.php
│   ├── LeadAutomationService.php
│   ├── TwilioService.php      # WhatsApp integration
│   ├── OpenAIService.php      # Whisper + GPT
│   ├── PagarMeService.php
│   └── ChavesNaMaoService.php
├── Models/
│   ├── Traits/
│   │   └── BelongsToTenant.php
│   └── *.php          # Eloquent models
└── Observers/
    └── LeadObserver.php # Auto-inicia atendimento IA
```

**Padrões de Controller**:
- Controllers em `app/Http/Controllers/` (sem namespaces aninhados)
- Use Services para lógica de negócio complexa
- Retorne JSON sempre: `return response()->json(['data' => $result]);`

**Services** (23 services em `app/Services/`):
- Services injetados via DI ou `app(OpenAIService::class)`
- Configuração via `.env` (ex: `EXCLUSIVA_OPENAI_API_KEY`)
- Services principais: `TenantService`, `LeadAutomationService`, `OpenAIService`, `TwilioService`, `PagarMeService`, `ChavesNaMaoService`, `ImportacaoImoveisService`

**Observers**:
- `LeadObserver@created`: Auto-inicia atendimento IA para leads da Chaves na Mão
- Registrado em `bootstrap/app.php`: `App\Models\Lead::observe(...)`

### Frontend (HTML/jQuery)
**Localização**: `public/app/` - Servido pelo mesmo servidor PHP!

**Arquitetura**:
```
public/
├── app/               # Admin/CRM (HTML puro)
│   ├── login.html     # Login unificado (auto-detecta role)
│   ├── dashboard.html # Dashboard com cards
│   ├── leads.html     # Gestão leads + botão "Iniciar IA"
│   ├── imoveis.html   # Gestão imóveis
│   ├── conversas.html # Chat estilo WhatsApp
│   └── configuracoes.html # Abas (perfil, integrações)
├── portal/            # Portal cliente (HTML)
│   └── index.html
├── js/                # Scripts compartilhados
│   └── login-utils.js # Funções de autenticação
└── css/
    └── glow.css       # Estilo neon/glow
```
### Frontend React (Novo - Principal)
**Localização**: `client/` - Build vai para `dist/public/` → copiado para `public/` no deploy

**Stack**:
- React 19 + TypeScript
- Vite (dev server + build)
- TailwindCSS v4
- Radix UI components
- React Router
- Axios para API calls

**Estrutura**:
```Frontend (React ou HTML) envia credenciais para `/api/auth/login`
2. Backend retorna token + user object com `role`
3. Frontend armazena token e redireciona baseado em role:
   - `super_admin` → Dashboard admin
   - `admin`/`corretor` → Dashboard admin
   - `cliente` → Portal cliente
4. Token salvo em `localStorage.setItem('token', ...)`
**Desenvolvimento**:
```bash
cd client
pnpm install           # Instalar deps
pnpm dev              # Dev server (http://localhost:3000)
pnpm build            # Build produção → dist/public/
```

**Proxy API** (dev): Vite configurado para proxy `/api` → backend em porta 8000

**API Communication** (ambos frontends)
**API Communication**:
```javascript
// Sempre use caminhos relativos - NUNCA localhost:3000!
fetch('/api/leads', {
    headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
    }
});
```

**Login Flow**:
1. `login.html`: Form simples, valida credenciais
2. Backend retorna token + user object com `role`
3. `LoginUtils.getRedirectForRole(role)` redireciona:
   - `super_admin` → `/app/dashboard.html`
   - `admin`/`corretor` → `/app/dashboard.html`
   - `cliente` → `/portal/`
4. Token salvo em `localStorage.setItem('token', ...)`

**Padrões UI**:
- TailwindCSS via CDN (sem build)
- jQuery 3.7.1 para AJAX
- Classes `glow-*` para estilo neon
- Feedback via `<div class="glow-feedback">` (sucesso/erro)

### Integrações Externas

**Twilio WhatsApp** (`app/Services/TwilioService.php`):
- Usado para enviar mensagens WhatsApp
- Config: `EXCLUSIVA_TWILIO_ACCOUNT_SID`, `EXCLUSIVA_TWILIO_AUTH_TOKEN`, `EXCLUSIVA_TWILIO_WHATSAPP_FROM`
- Método principal: `sendMessage($to, $body)` - formato `$to` = `whatsapp:+5531999999999`

**OpenAI** (`app/Services/OpenAIService.php`):
- Whisper API para transcrição de áudio WhatsApp
- GPT-4o-mini para geração de respostas IA
- Config: `EXCLUSIVA_OPENAI_API_KEY`, `EXCLUSIVA_OPENAI_MODEL`
- Métodos: `transcribeAudio($audioPath)`, `chatCompletion($system, $user)`

**Chaves na Mão** (`app/Services/ChavesNaMaoService.php`):
- Webhook recebe leads: `POST /webhook/chaves-na-mao`
- `LeadObserver@created` detecta leads desta origem
- Auto-inicia atendimento IA via `LeadAutomationService`

**Automação IA**:
```php
// LeadAutomationService flow:
1. Valida WhatsApp brasileiro
2. Cria/reutiliza Conversa
3. OpenAI gera msg personalizada (contexto do lead)
4. Envia via Twilio
5. Registra mensagem + atualiza status
```

**Botão Manual** em `leads.html`:
- Cada card tem ícone 🤖
- Chama `/api/admin/leads/{id}/iniciar-atendimento`
- Útil para leads que não foram auto-processados

## 🔧 Troubleshooting Comum

### Backend não responde
1. **Verificar MySQL**: `Get-Service mysql` (deve estar Running)
2. **Criar banco se não existe**: `mysql -u root -e "CREATE DATABASE exclusiva"`
3. **Verificar .env**: Confirme `DB_CONNECTION=mysql`, `DB_DATABASE=exclusiva`
4. **Logs**: `storage/logs/lumen-YYYY-MM-DD.log`
5. **Rpowershell
   # Matar processos PHP
   Get-Process php | Stop-Process -Force
   # Reiniciar
   php -S 127.0.0.1:8000 -t public router.php
   ```

### Debug rápido com scripts
```powershell
# Verificar se tudo está OK
php check_db.php              # Conexão e estrutura do banco
php check_tenant.php          # Tenant atual
php test_login.php            # Testar autenticação
php diagnose_404.php          # Debug de rotas 404

# Logs do sistema
php check_system_logs.php     # Ver últimos logs
Get-Content storage/logs/lumen-*.log -Tail 50  # Últimas 50 linhas
php -S 127.0.0SSH via `deploy-ssh.ps1` ou `deploy.sh`
- **Build process**: 
  1. `pnpm build` gera `dist/public/` (React)
  2. Script copia `dist/public/*` para `public/`
  3. `composer dump-autoload --optimize`
  4. Ajusta permissões em `storage/` e `bootstrap/cache/`
- **Produção**: Hostinger com Node.js, composer, MySQL
- **Ambiente**: `.env` para dev, `.env_prod_fixed` para referência
- **CI/CD**: Workflows GitHub Actions (`.github/workflows/`)

**CRÍTICO**: Deploy SSH agora copia build React (`dist/public/*`) para `public/`, substituindo o frontend antigo HTML/jQuery.
1. **Verificar backend**: `http://127.0.0.1:8000/api/health` deve retornar JSON
2. **Acessar URL correta**: `http://127.0.0.1:8000/app/` (com `/app/`)
3. **CORS não é problema**: Frontend e API no mesmo domínio!
4. **Token**: Limpar localStorage se necessário: `localStorage.clear()`
5. **Debug login**: Abra DevTools (F12) → Console para ver logs detalhados
6. **Verificar arquivos**: Confirme que `public/app/*.html` existem

### Sistema de Autenticação
- **Simples e direto**: localStorage + Bearer token
- **Login aceita**: `senha` ou `password` (backend suporta ambos)
- **Redireciona automaticamente**: Se não autenticado, vai para login
- **Persiste sessão**: Token fica salvo entre reloads

### Credenciais de teste
- **Super Admin**: `admin@exclusiva.com` / `password`
- Para criar novos users: `create_superadmin.php` como exemplo

## 📚 Arquivos-Chave

###public/js/login-utils.js` - Utilitários de autenticação compartilhados
- `public/css/glow.css` - Estilo neon/glow padrão
- `START.bat` - Script de inicialização simples
- `router.php` - Router customizado para PHP built-in server
- `SERVIDOR_UNICO.md` - Documentação completa do novo setup

### Deploy & Infraestrutura
- **Deploy**: Via FTP manual ou scripts deploy (docs em `DEPLOY_*.md`)
- **Produção**: Hostinger com composer, migrations e cache
- **Ambiente**: `.env` para dev, `.env_prod_fixed` para referência de produção
- **CI/CD**: Workflows GitHub Actions (`.github/workflows/`)

---

**Dica Desenvolvimento**: Use os 100+ scripts na raiz para debug rápido:
```powershell
# Diagnóstico
php check_db.php           # DB health
php check_tenant.php       # Tenant atual
php check_users_roles.php  # Listar usuários

# Criar dados de teste
php create_superadmin.php  # Novo super admin
php criar_lead_teste_chavesnamao.php  # Lead teste

# Testar APIs
php test_login.php         # Auth flow
php test_api_simples.php   # Endpoints básicos
php test_atendimento_ia.php # IA automation
``
- `bootstrap/app.php` - Configuração principal do Lumen
- `routes/web.php` - Rotas principais (auth, dashboard)
- `app/Http/Middleware/ResolveTenant.php` - Lógica de tenant resolution
- `app/Services/TenantService.php` - Gerenciamento de tenants

### Frontend
- `public/app/*.html` - Páginas HTML/jQuery (servidor único)
- `START.bat` - Script de inicialização simples
- `SERVIDOR_UNICO.md` - Documentação completa do novo setup

---

**Dica**: Para desenvolvimento rápido, use os scripts em raiz como `create_superadmin.php`, `check_db.php`, `test_login.php`