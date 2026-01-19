# Fase 2: Implementação da Estrutura Multi-Tenant

## 📋 Resumo Executivo

Nesta fase, transformamos o projeto Exclusiva de uma aplicação single-tenant para uma plataforma SaaS multi-tenant. Cada imobiliária (tenant) terá seus dados completamente isolados no mesmo banco de dados.

---

## 🎯 Objetivos Alcançados

### ✅ 1. Criação da Tabela `tenants`
**Arquivo:** `database/migrations/2025_12_18_100000_create_tenants_table.php`

A tabela central que armazena informações de cada imobiliária cliente:

```sql
CREATE TABLE tenants (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    domain VARCHAR(255) UNIQUE,
    slug VARCHAR(255) UNIQUE,
    theme ENUM('classico', 'bauhaus'),
    primary_color VARCHAR(7),
    secondary_color VARCHAR(7),
    logo_url VARCHAR(500),
    subscription_status ENUM('active', 'inactive', 'suspended', 'expired'),
    subscription_plan VARCHAR(50),
    subscription_expires_at TIMESTAMP,
    subscription_started_at TIMESTAMP,
    pagar_me_customer_id VARCHAR(255),
    pagar_me_subscription_id VARCHAR(255),
    api_key_pagar_me VARCHAR(255),
    api_key_apm_imoveis VARCHAR(255),
    api_key_neca VARCHAR(255),
    api_token VARCHAR(255),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(20),
    description TEXT,
    is_active BOOLEAN,
    max_users INT,
    max_properties INT,
    max_leads INT,
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);
```

**Campos Principais:**
- `domain`: Domínio personalizado da imobiliária (ex: imobiliariajoao.com.br)
- `theme`: Tema escolhido (Clássico ou Bauhaus)
- `subscription_status`: Status da assinatura
- `api_key_*`: Chaves de API para integrações
- `max_users`, `max_properties`, `max_leads`: Limites de recursos

---

### ✅ 2. Adição de `tenant_id` às Tabelas Existentes
**Arquivo:** `database/migrations/2025_12_18_100001_add_tenant_id_to_existing_tables.php`

Adicionamos a coluna `tenant_id` a todas as tabelas que contêm dados específicos de uma imobiliária:

| Tabela | Alterações |
|--------|-----------|
| `users` | + `tenant_id`, + `role` (super_admin, admin, corretor, cliente), + índice único (email, tenant_id) |
| `imo_properties` | + `tenant_id`, + índice |
| `leads` | + `tenant_id`, + índice |
| `conversas` | + `tenant_id`, + índice |
| `mensagens` | + `tenant_id`, + índice |
| `atividades` | + `tenant_id`, + índice |
| `lead_documents` | + `tenant_id`, + índice |
| `lead_property_matches` | + `tenant_id`, + índice |
| `app_settings` | + `tenant_id`, + índice |

**Exemplo de Alteração:**
```php
Schema::table('users', function (Blueprint $table) {
    $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
    $table->foreign('tenant_id')
        ->references('id')
        ->on('tenants')
        ->onDelete('cascade');
    $table->index('tenant_id');
    $table->unique(['email', 'tenant_id'], 'unique_email_tenant');
});
```

**Benefícios:**
- ✅ Isolamento de dados por tenant
- ✅ Integridade referencial com foreign keys
- ✅ Índices para performance
- ✅ Exclusão em cascata (ao deletar tenant, todos seus dados são deletados)

---

### ✅ 3. Criação da Tabela `subscriptions`
**Arquivo:** `database/migrations/2025_12_18_100002_create_subscriptions_table.php`

Tabela para gerenciar assinaturas de cada imobiliária:

```sql
CREATE TABLE subscriptions (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT UNIQUE,
    plan_id VARCHAR(50),
    plan_name VARCHAR(255),
    plan_amount DECIMAL(10,2),
    plan_interval VARCHAR(20),
    status ENUM('active', 'past_due', 'canceled', 'paused'),
    status_reason VARCHAR(255),
    current_period_start TIMESTAMP,
    current_period_end TIMESTAMP,
    canceled_at TIMESTAMP,
    pagar_me_subscription_id VARCHAR(255),
    pagar_me_customer_id VARCHAR(255),
    pagar_me_card_id VARCHAR(255),
    payment_method VARCHAR(50),
    card_last_four VARCHAR(4),
    card_brand VARCHAR(20),
    failed_attempts INT,
    next_retry_at TIMESTAMP,
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Campos Principais:**
- `plan_id`: Identificador do plano (ex: 'basic', 'professional', 'enterprise')
- `status`: Status da assinatura
- `current_period_end`: Quando a assinatura expira
- `pagar_me_*`: IDs do Pagar.me para sincronização
- `failed_attempts`: Tentativas de cobrança falhadas

---

### ✅ 4. Criação da Tabela `tenant_configs`
**Arquivo:** `database/migrations/2025_12_18_100003_create_tenant_configs_table.php`

Tabela para armazenar configurações específicas de cada tenant:

```sql
CREATE TABLE tenant_configs (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT UNIQUE,
    api_key_pagar_me TEXT,
    api_key_apm_imoveis TEXT,
    api_key_neca TEXT,
    primary_color VARCHAR(7),
    secondary_color VARCHAR(7),
    accent_color VARCHAR(7),
    logo_url VARCHAR(500),
    favicon_url VARCHAR(500),
    smtp_host VARCHAR(255),
    smtp_port INT,
    smtp_username VARCHAR(255),
    smtp_password TEXT,
    smtp_from_email VARCHAR(255),
    smtp_from_name VARCHAR(255),
    notify_new_leads BOOLEAN,
    notify_new_properties BOOLEAN,
    notify_new_messages BOOLEAN,
    notification_email VARCHAR(255),
    max_images_per_property INT,
    max_properties INT,
    require_approval_for_properties BOOLEAN,
    max_leads INT,
    auto_assign_leads BOOLEAN,
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Campos Principais:**
- Chaves de API para integrações
- Configurações de tema (cores, logo)
- Configurações de email (SMTP)
- Configurações de notificações
- Limites de recursos

---

## 🏗️ Modelos Criados/Modificados

### 1. **Modelo Tenant** (Novo)
**Arquivo:** `app/Models/Tenant.php`

```php
class Tenant extends Model
{
    // Relacionamentos
    public function users() { ... }
    public function properties() { ... }
    public function leads() { ... }
    public function subscription() { ... }
    public function config() { ... }

    // Scopes
    public function scopeActive($query) { ... }
    public function scopeSubscribed($query) { ... }
    public function scopeByDomain($query, $domain) { ... }

    // Métodos
    public function isSubscribed(): bool { ... }
    public function isActive(): bool { ... }
    public function canAddUsers(): bool { ... }
    public function canAddProperties(): bool { ... }
    public function canAddLeads(): bool { ... }
    public function generateApiToken(): string { ... }
    public function getAdminUser() { ... }
    public function getCorrectores() { ... }
    public function getClientes() { ... }
    public function suspendSubscription(string $reason = null): void { ... }
    public function activateSubscription(): void { ... }
}
```

**Funcionalidades:**
- ✅ Relacionamentos com todas as entidades
- ✅ Scopes para queries comuns
- ✅ Métodos para validar estado
- ✅ Métodos para gerenciar assinatura

---

### 2. **Modelo Subscription** (Novo)
**Arquivo:** `app/Models/Subscription.php`

```php
class Subscription extends Model
{
    // Relacionamentos
    public function tenant() { ... }

    // Scopes
    public function scopeActive($query) { ... }
    public function scopePastDue($query) { ... }
    public function scopeCanceled($query) { ... }
    public function scopeExpiring($query, $days = 7) { ... }

    // Métodos
    public function isActive(): bool { ... }
    public function isPastDue(): bool { ... }
    public function isCanceled(): bool { ... }
    public function isExpiring($days = 7): bool { ... }
    public function isExpired(): bool { ... }
    public function cancel(string $reason = null): void { ... }
    public function markAsPastDue(string $reason = null): void { ... }
    public function markAsActive(): void { ... }
    public function updatePeriod($startDate, $endDate): void { ... }
    public function getDaysUntilExpiration(): ?int { ... }
    public function getFormattedAmount(): string { ... }
    public function getFormattedInterval(): string { ... }
}
```

**Funcionalidades:**
- ✅ Rastreamento de status de assinatura
- ✅ Métodos para gerenciar período
- ✅ Formatação de valores

---

### 3. **Modelo TenantConfig** (Novo)
**Arquivo:** `app/Models/TenantConfig.php`

```php
class TenantConfig extends Model
{
    // Relacionamentos
    public function tenant() { ... }

    // Métodos
    public function getSmtpConfig(): array { ... }
    public function setSmtpConfig(array $config): void { ... }
    public function getApiKeys(): array { ... }
    public function setApiKey(string $service, string $key): void { ... }
    public function getThemeColors(): array { ... }
    public function setThemeColors(array $colors): void { ... }
    public function getNotificationSettings(): array { ... }
    public function setNotificationSettings(array $settings): void { ... }
    public function getLimits(): array { ... }
    public function setLimits(array $limits): void { ... }
}
```

**Funcionalidades:**
- ✅ Gerenciamento de configurações SMTP
- ✅ Gerenciamento de chaves de API
- ✅ Gerenciamento de cores do tema
- ✅ Gerenciamento de notificações
- ✅ Gerenciamento de limites

---

### 4. **Modelo User** (Modificado)
**Arquivo:** `app/Models/User.php`

**Alterações:**
```php
// Adicionado ao fillable
'tenant_id',
'role',

// Novos relacionamentos
public function tenant() { ... }
public function conversas() { ... }
public function leads() { ... }

// Novos scopes
public function scopeForTenant($query, $tenantId) { ... }
public function scopeAdmins($query) { ... }
public function scopeCorrectores($query) { ... }
public function scopeClientes($query) { ... }

// Novos métodos
public function isSuperAdmin(): bool { ... }
public function isAdmin(): bool { ... }
public function isCorretor(): bool { ... }
public function isCliente(): bool { ... }
public function canManageTenant(): bool { ... }
```

---

## 🔐 Middleware e Serviços

### 1. **Middleware ResolveTenant**
**Arquivo:** `app/Http/Middleware/ResolveTenant.php`

Responsável por:
1. Extrair o domínio da requisição
2. Buscar o tenant correspondente no banco de dados
3. Registrar o tenant no container da aplicação
4. Adicionar `tenant_id` aos atributos da requisição

```php
public function handle(Request $request, Closure $next)
{
    $host = $request->getHost();
    
    // Em desenvolvimento, usar primeiro tenant
    if ($this->isDevelopment($host)) {
        $tenant = Tenant::first();
        if ($tenant) {
            app()->instance('tenant', $tenant);
            $request->attributes->set('tenant_id', $tenant->id);
        }
        return $next($request);
    }
    
    // Em produção, buscar por domínio
    $tenant = Tenant::byDomain($host)->first();
    
    if (!$tenant || !$tenant->isActive()) {
        return response()->json(['error' => 'Tenant not found'], 404);
    }
    
    app()->instance('tenant', $tenant);
    $request->attributes->set('tenant_id', $tenant->id);
    
    return $next($request);
}
```

---

### 2. **Middleware ValidateTenantAuth**
**Arquivo:** `app/Http/Middleware/ValidateTenantAuth.php`

Responsável por:
1. Validar se há um tenant no contexto
2. Verificar se o usuário autenticado pertence ao tenant
3. Permitir super admin acessar qualquer tenant

```php
public function handle(Request $request, Closure $next)
{
    if (!app()->bound('tenant') || !$request->attributes->get('tenant_id')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    
    if ($request->user()) {
        $tenantId = $request->attributes->get('tenant_id');
        $userTenantId = $request->user()->tenant_id;
        
        // Super admin pode acessar qualquer tenant
        if ($request->user()->isSuperAdmin()) {
            return $next($request);
        }
        
        // Usuário normal deve pertencer ao tenant
        if ($userTenantId !== $tenantId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
    }
    
    return $next($request);
}
```

---

### 3. **Serviço TenantService**
**Arquivo:** `app/Services/TenantService.php`

Serviço centralizado para operações com tenants:

```php
class TenantService
{
    // Obter tenant atual
    public function current(): ?Tenant { ... }
    public function currentId(): ?int { ... }
    public function hasCurrent(): bool { ... }
    
    // Buscar tenants
    public function findByDomain(string $domain): ?Tenant { ... }
    public function findById(int $id): ?Tenant { ... }
    public function findBySlug(string $slug): ?Tenant { ... }
    
    // CRUD
    public function create(array $data): Tenant { ... }
    public function update(Tenant $tenant, array $data): Tenant { ... }
    public function delete(Tenant $tenant): bool { ... }
    
    // Gerenciar status
    public function activate(Tenant $tenant): Tenant { ... }
    public function deactivate(Tenant $tenant): Tenant { ... }
    public function suspendSubscription(Tenant $tenant, string $reason = null): Tenant { ... }
    public function activateSubscription(Tenant $tenant): Tenant { ... }
    
    // Listar
    public function all(int $perPage = 15) { ... }
    public function active(int $perPage = 15) { ... }
    public function subscribed(int $perPage = 15) { ... }
    
    // Estatísticas
    public function getStats(Tenant $tenant): array { ... }
}
```

---

### 4. **Trait BelongsToTenant** (Implementação Futura)
**Arquivo:** `app/Traits/BelongsToTenant.php`

Trait para aplicar Global Scope automaticamente em modelos:

```php
class BelongsToTenant implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if ($tenantId = $this->getTenantId()) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }
    
    protected function getTenantId()
    {
        if (app()->bound('tenant')) {
            return app('tenant')->id ?? null;
        }
        
        if (app()->bound('request')) {
            return app('request')->attributes->get('tenant_id');
        }
        
        return null;
    }
}
```

---

## 🔄 Fluxo de Requisição Multi-Tenant

```
1. Usuário acessa: imobiliariajoao.com.br/api/leads
                            ↓
2. ResolveTenant Middleware
   - Extrai domínio: "imobiliariajoao.com.br"
   - Busca tenant no banco: SELECT * FROM tenants WHERE domain = ?
   - Registra no container: app()->instance('tenant', $tenant)
   - Adiciona à requisição: $request->attributes->set('tenant_id', 5)
                            ↓
3. ValidateTenantAuth Middleware
   - Valida se há tenant no contexto
   - Verifica se usuário pertence ao tenant
   - Permite super admin acessar qualquer tenant
                            ↓
4. Controller (LeadsController@index)
   - Recebe requisição com tenant_id = 5
   - Executa: Lead::where('tenant_id', 5)->get()
   - Retorna apenas leads do tenant 5
                            ↓
5. Response
   - JSON com dados isolados do tenant
```

---

## 📊 Estrutura de Dados Isolada

### Exemplo: Dois Tenants com Dados Isolados

**Tenant 1: Imobiliária João**
- ID: 1
- Domain: imobiliariajoao.com.br
- Usuários: 3 (1 admin, 2 corretores)
- Imóveis: 45
- Leads: 120

**Tenant 2: Imobiliária Marcos**
- ID: 2
- Domain: imobiliariamarcos.com.br
- Usuários: 5 (1 admin, 4 corretores)
- Imóveis: 87
- Leads: 250

**Banco de Dados (Único):**
```
users:
├── ID 1, tenant_id 1, João Admin
├── ID 2, tenant_id 1, Corretor João 1
├── ID 3, tenant_id 1, Corretor João 2
├── ID 4, tenant_id 2, Marcos Admin
├── ID 5, tenant_id 2, Corretor Marcos 1
└── ...

imo_properties:
├── ID 1, tenant_id 1, Imóvel João 1
├── ID 2, tenant_id 1, Imóvel João 2
├── ...
├── ID 46, tenant_id 2, Imóvel Marcos 1
├── ID 47, tenant_id 2, Imóvel Marcos 2
└── ...

leads:
├── ID 1, tenant_id 1, Lead João 1
├── ID 2, tenant_id 1, Lead João 2
├── ...
├── ID 121, tenant_id 2, Lead Marcos 1
├── ID 122, tenant_id 2, Lead Marcos 2
└── ...
```

**Isolamento:**
- Usuário João (tenant_id=1) só vê dados com tenant_id=1
- Usuário Marcos (tenant_id=2) só vê dados com tenant_id=2
- Super Admin pode acessar todos os dados

---

## 🚀 Próximas Etapas

### Fase 3: Painel Super Admin
- Criar controllers para gerenciar tenants
- Criar views para CRUD de tenants
- Implementar dashboard global

### Fase 4: Integração Pagar.me
- Integrar API do Pagar.me
- Implementar webhooks
- Criar fluxo de pagamento

### Fase 5: Domínios e Temas
- Implementar sistema de domínios personalizados
- Criar temas Clássico e Bauhaus
- Permitir customização de cores

### Fase 6: Portal Cliente Final
- Cadastro de clientes
- Sistema de intenções
- Notificações

---

## 📝 Checklist de Implementação

- [x] Criar tabela `tenants`
- [x] Criar tabela `subscriptions`
- [x] Criar tabela `tenant_configs`
- [x] Adicionar `tenant_id` a todas as tabelas
- [x] Criar modelo `Tenant`
- [x] Criar modelo `Subscription`
- [x] Criar modelo `TenantConfig`
- [x] Modificar modelo `User`
- [x] Criar middleware `ResolveTenant`
- [x] Criar middleware `ValidateTenantAuth`
- [x] Criar serviço `TenantService`
- [x] Criar trait `BelongsToTenant`
- [ ] Registrar middlewares em `bootstrap/app.php`
- [ ] Atualizar controllers existentes
- [ ] Criar testes automatizados
- [ ] Documentar API

---

## 🔗 Arquivos Criados

| Arquivo | Tipo | Descrição |
|---------|------|-----------|
| `database/migrations/2025_12_18_100000_create_tenants_table.php` | Migration | Tabela tenants |
| `database/migrations/2025_12_18_100001_add_tenant_id_to_existing_tables.php` | Migration | Adiciona tenant_id |
| `database/migrations/2025_12_18_100002_create_subscriptions_table.php` | Migration | Tabela subscriptions |
| `database/migrations/2025_12_18_100003_create_tenant_configs_table.php` | Migration | Tabela tenant_configs |
| `app/Models/Tenant.php` | Model | Modelo Tenant |
| `app/Models/Subscription.php` | Model | Modelo Subscription |
| `app/Models/TenantConfig.php` | Model | Modelo TenantConfig |
| `app/Http/Middleware/ResolveTenant.php` | Middleware | Resolver tenant |
| `app/Http/Middleware/ValidateTenantAuth.php` | Middleware | Validar tenant auth |
| `app/Services/TenantService.php` | Service | Serviço de tenant |
| `app/Traits/BelongsToTenant.php` | Trait | Global scope |

---

## 📚 Documentação

- ✅ Análise do projeto: `/home/ubuntu/analise_projeto_exclusiva.md`
- ✅ Arquitetura SaaS: `/home/ubuntu/exclusiva_saas_architecture.md`
- ✅ Fase 2 (este documento): `/home/ubuntu/FASE2_MULTI_TENANT_IMPLEMENTATION.md`

---

**Data:** 2025-12-18
**Status:** ✅ Completo
**Próximo Passo:** Fase 3 - Painel Super Admin
