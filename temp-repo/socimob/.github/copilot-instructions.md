# 🛠️ Instruções para Agentes de Codificação AI

## Visão Geral do Projeto
**SOCIMOB/Exclusiva**: Plataforma SaaS multi-tenant para gestão imobiliária. Stack: **Lumen 10** + **HTML/jQuery** + **MySQL** (single-server architecture).

### Arquitetura-Chave
- **Single-server design**: Frontend e backend no MESMO servidor PHP (porta 8000)
- **Lumen, não Laravel**: Sem facades complexos, rotas diretas em `routes/web.php`
- **Multi-tenancy via trait**: `BelongsToTenant` auto-injeta `tenant_id` em models
- **Sem build tools**: CDN-only frontend (jQuery 3.7.1 + TailwindCSS), zero npm/Vite
- **Token auth simples**: base64 encoding `user_id|timestamp|secret` (não JWT)

## 🚀 Início Rápido (Desenvolvimento Local)

### Pré-requisitos
- PHP 8.1+ (com extensões: mysqli, pdo_mysql, mbstring, openssl)
- MySQL rodando (XAMPP ou standalone)
- Banco `exclusiva` criado (ou será auto-criado)
- **Node.js NÃO necessário** ✅

### Execução (Um Comando)
```bash
# Windows (raiz do projeto)
START.bat

# Ou manual
php -S 127.0.0.1:8000 -t public router.php
```

### Acesso
- **Admin/CRM**: `http://127.0.0.1:8000/app/`
- **Portal Cliente**: `http://127.0.0.1:8000/portal/`
- **API Health**: `http://127.0.0.1:8000/api/health`

### Credenciais
- **Super Admin**: `admin@exclusiva.com` / `password`
- **Criar novos**: Veja scripts na raiz (`create_superadmin.php`, `quick_create_user.php`)

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
├── admin.php         # Rotas administrativas
├── portal.php        # Portal do cliente
├── subscriptions.php # Gestão de assinaturas
└── themes.php        # Temas personalizáveis
```

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

**Services**:
- Services injetados via DI ou `app(OpenAIService::class)`
- Configuração via `.env` (ex: `EXCLUSIVA_OPENAI_API_KEY`)

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
5. **Reiniciar servidor**:
   ```bash
   # Matar processos PHP
   Get-Process php | Stop-Process -Force
   # Reiniciar
   php -S 127.0.0.1:8000 -t public router.php
   ```

### Frontend não carrega
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

### Arquitetura
- `docs/exclusiva_saas_architecture.md` - Diagrama e visão geral
- `docs/FASE2_MULTI_TENANT_IMPLEMENTATION.md` - Implementação multi-tenant
- `docs/INDICE_DOCUMENTACAO.md` - Índice completo

### Backend
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