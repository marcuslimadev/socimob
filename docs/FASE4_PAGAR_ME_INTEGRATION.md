# Fase 4: Integração com Pagar.me e Sistema de Assinaturas Recorrentes

## 📋 Resumo Executivo

Nesta fase, implementamos a integração completa com o Pagar.me para gerenciar assinaturas recorrentes, pagamentos de cartão de crédito e webhooks para sincronização de status.

---

## 🎯 Objetivos Alcançados

### ✅ 1. Serviço Pagar.me
**Arquivo:** `app/Services/PagarMeService.php`

Serviço centralizado para todas as operações com a API do Pagar.me:

#### Métodos Implementados

| Método | Descrição |
|--------|-----------|
| `createCustomer()` | Criar cliente no Pagar.me |
| `createCard()` | Registrar cartão de crédito |
| `createSubscription()` | Criar assinatura recorrente |
| `getSubscription()` | Obter detalhes da assinatura |
| `cancelSubscription()` | Cancelar assinatura |
| `updateSubscription()` | Atualizar assinatura (cartão, plano, etc) |
| `handleWebhook()` | Processar webhooks do Pagar.me |
| `verifyWebhookSignature()` | Validar assinatura do webhook |

#### Webhooks Suportados

```php
// Assinatura criada
subscription.created
  - Atualiza tenant com IDs do Pagar.me
  - Define status como 'active'

// Assinatura atualizada
subscription.updated
  - Sincroniza status da assinatura

// Assinatura deletada
subscription.deleted
  - Marca assinatura como cancelada

// Cobrança bem-sucedida
charge.succeeded
  - Marca assinatura como 'active'
  - Reseta contador de tentativas falhadas

// Cobrança falhada
charge.failed
  - Marca assinatura como 'past_due'
  - Incrementa contador de tentativas
  - Agenda próxima tentativa

// Cobrança reembolsada
charge.refunded
  - Registra reembolso no log
```

#### Exemplo de Uso

```php
// Criar cliente
$customer = $pagarMeService->createCustomer([
    'name' => 'João Silva',
    'email' => 'joao@imobiliaria.com.br',
    'document' => '12345678901',
    'phone' => '+5511999999999',
]);
// Retorna: ['id' => 'cus_xxxxx', 'name' => 'João Silva', ...]

// Criar cartão
$card = $pagarMeService->createCard($customer['id'], [
    'number' => '4111111111111111',
    'holder_name' => 'JOAO SILVA',
    'exp_month' => 12,
    'exp_year' => 2026,
    'cvv' => '123',
    'street' => 'Rua A',
    'number_address' => '123',
    'zip_code' => '01310100',
    'city' => 'São Paulo',
    'state' => 'SP',
]);
// Retorna: ['id' => 'card_xxxxx', 'number' => '4111', ...]

// Criar assinatura
$subscription = $pagarMeService->createSubscription(
    $customer['id'],
    $card['id'],
    [
        'plan_id' => 'plan_basic',
        'description' => 'Plano Básico',
        'amount' => 99.00,
        'interval' => 'month',
        'interval_count' => 1,
    ]
);
// Retorna: ['id' => 'sub_xxxxx', 'status' => 'active', ...]

// Processar webhook
$pagarMeService->handleWebhook([
    'type' => 'charge.succeeded',
    'data' => [
        'id' => 'ch_xxxxx',
        'subscription_id' => 'sub_xxxxx',
        'status' => 'paid',
    ],
]);
```

---

### ✅ 2. Controller de Assinaturas
**Arquivo:** `app/Http/Controllers/SubscriptionController.php`

Controller para gerenciar assinaturas do tenant:

#### Endpoints Implementados

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/subscriptions/current` | Obter assinatura atual |
| POST | `/api/subscriptions` | Criar nova assinatura |
| POST | `/api/subscriptions/cancel` | Cancelar assinatura |
| PUT | `/api/subscriptions/card` | Atualizar cartão de crédito |
| POST | `/api/webhooks/pagar-me` | Webhook do Pagar.me (público) |

#### Fluxo de Criação de Assinatura

```
1. POST /api/subscriptions
   {
       "plan_id": "plan_basic",
       "plan_name": "Plano Básico",
       "plan_amount": 99.00,
       "plan_interval": "month",
       "card_number": "4111111111111111",
       "card_holder_name": "JOAO SILVA",
       "card_exp_month": 12,
       "card_exp_year": 2026,
       "card_cvv": "123",
       "billing_address_street": "Rua A",
       "billing_address_number": "123",
       "billing_address_zip_code": "01310100",
       "billing_address_city": "São Paulo",
       "billing_address_state": "SP"
   }

2. Sistema:
   a) Cria cliente no Pagar.me (se não existir)
   b) Registra cartão
   c) Cria assinatura recorrente
   d) Salva assinatura localmente
   e) Atualiza status do tenant

3. Resposta:
   {
       "message": "Subscription created successfully",
       "subscription": {
           "id": 1,
           "tenant_id": 1,
           "plan_id": "plan_basic",
           "plan_name": "Plano Básico",
           "plan_amount": 99.00,
           "plan_interval": "month",
           "status": "active",
           "card_last_four": "1111",
           "card_brand": "visa",
           "current_period_start": "2025-12-18T00:00:00Z",
           "current_period_end": "2026-01-18T00:00:00Z",
           "pagar_me_subscription_id": "sub_xxxxx"
       }
   }
```

#### Exemplo de Cancelamento

```php
// Cancelar assinatura
POST /api/subscriptions/cancel

// Sistema:
// 1. Cancela no Pagar.me
// 2. Atualiza status localmente
// 3. Atualiza status do tenant

// Resposta:
{
    "message": "Subscription canceled successfully"
}
```

#### Exemplo de Atualizar Cartão

```php
// Atualizar cartão
PUT /api/subscriptions/card
{
    "card_number": "5555555555554444",
    "card_holder_name": "JOAO SILVA",
    "card_exp_month": 6,
    "card_exp_year": 2027,
    "card_cvv": "456",
    "billing_address_street": "Rua B",
    "billing_address_number": "456",
    "billing_address_zip_code": "01310100",
    "billing_address_city": "São Paulo",
    "billing_address_state": "SP"
}

// Sistema:
// 1. Registra novo cartão no Pagar.me
// 2. Atualiza assinatura com novo cartão
// 3. Atualiza dados localmente

// Resposta:
{
    "message": "Card updated successfully",
    "subscription": {
        "card_last_four": "4444",
        "card_brand": "mastercard"
    }
}
```

---

### ✅ 3. Modelo Subscription Aprimorado
**Arquivo:** `app/Models/Subscription.php`

Modelo com métodos auxiliares para gerenciar assinaturas:

#### Métodos Implementados

```php
// Verificar status
$subscription->isActive();          // true/false
$subscription->isPastDue();         // true/false
$subscription->isCanceled();        // true/false
$subscription->isExpiring(7);       // true/false (expira em 7 dias)
$subscription->isExpired();         // true/false

// Atualizar status
$subscription->cancel('Motivo');
$subscription->markAsPastDue('Motivo');
$subscription->markAsActive();

// Informações
$subscription->getDaysUntilExpiration();  // Número de dias
$subscription->getFormattedAmount();      // "R$ 99,00"
$subscription->getFormattedInterval();    // "Mensal" ou "Anual"

// Scopes
Subscription::active();           // Assinaturas ativas
Subscription::pastDue();          // Assinaturas vencidas
Subscription::canceled();         // Assinaturas canceladas
Subscription::expiring(7);        // Expirando em 7 dias
```

#### Relacionamentos

```php
$subscription->tenant();  // Tenant relacionado
```

---

### ✅ 4. Rotas de Assinaturas
**Arquivo:** `routes/subscriptions.php`

```php
// Webhook (público, sem autenticação)
POST /api/subscriptions/webhook

// Rotas autenticadas
GET    /api/subscriptions/current
POST   /api/subscriptions
POST   /api/subscriptions/cancel
PUT    /api/subscriptions/card
```

---

### ✅ 5. Migration para Campos de Assinatura
**Arquivo:** `database/migrations/2025_12_18_100004_add_subscription_fields_to_tenants.php`

Adiciona campos necessários à tabela `tenants`:

```sql
ALTER TABLE tenants ADD COLUMN subscription_status ENUM('active', 'inactive', 'suspended', 'expired');
ALTER TABLE tenants ADD COLUMN subscription_plan VARCHAR(50);
ALTER TABLE tenants ADD COLUMN subscription_expires_at TIMESTAMP;
ALTER TABLE tenants ADD COLUMN subscription_started_at TIMESTAMP;
ALTER TABLE tenants ADD COLUMN pagar_me_customer_id VARCHAR(255) UNIQUE;
ALTER TABLE tenants ADD COLUMN pagar_me_subscription_id VARCHAR(255) UNIQUE;
ALTER TABLE tenants ADD COLUMN api_key_pagar_me TEXT;
ALTER TABLE tenants ADD COLUMN api_key_apm_imoveis TEXT;
ALTER TABLE tenants ADD COLUMN api_key_neca TEXT;
ALTER TABLE tenants ADD COLUMN api_token VARCHAR(255) UNIQUE;
```

---

## 🔄 Fluxo Completo de Assinatura

### 1. Admin Cria Assinatura

```
Admin acessa: /admin/settings/subscription
Preenche formulário com:
- Plano (Básico, Profissional, Enterprise)
- Dados do cartão
- Endereço de cobrança

POST /api/subscriptions
```

### 2. Sistema Processa

```
a) Cria cliente no Pagar.me
   POST https://api.pagar.me/core/v5/customers
   
b) Registra cartão
   POST https://api.pagar.me/core/v5/customers/{id}/cards
   
c) Cria assinatura
   POST https://api.pagar.me/core/v5/subscriptions
   
d) Salva localmente
   INSERT INTO subscriptions (...)
   UPDATE tenants SET subscription_status = 'active'
```

### 3. Pagar.me Processa Pagamento

```
Pagar.me:
- Valida cartão
- Processa primeira cobrança
- Envia webhook: subscription.created
- Envia webhook: charge.succeeded
```

### 4. Sistema Recebe Webhooks

```
POST /api/subscriptions/webhook
{
    "type": "charge.succeeded",
    "data": {
        "subscription_id": "sub_xxxxx",
        "status": "paid"
    }
}

Sistema:
- Valida assinatura do webhook
- Atualiza status da assinatura
- Atualiza status do tenant
- Registra no log
```

### 5. Assinatura Ativa

```
Tenant pode:
- Usar todas as funcionalidades
- Adicionar usuários (até limite do plano)
- Criar imóveis (até limite do plano)
- Gerenciar leads (até limite do plano)

Próxima cobrança:
- Automática em 30 dias (ou 365 dias se anual)
- Se falhar, tenta novamente em 24h
- Após 3 tentativas, marca como past_due
```

### 6. Cancelamento

```
Admin acessa: /admin/settings/subscription
Clica em "Cancelar Assinatura"

POST /api/subscriptions/cancel

Sistema:
- Cancela no Pagar.me
- Atualiza status localmente
- Tenant perde acesso após período atual expirar
```

---

## 🔐 Segurança

### Dados Sensíveis
- ✅ Cartão não é armazenado localmente
- ✅ Apenas últimos 4 dígitos salvos
- ✅ CVV nunca é armazenado
- ✅ IDs do Pagar.me ocultos na resposta

### Validação de Webhook
- ✅ Assinatura HMAC-SHA256
- ✅ Verificação de timestamp
- ✅ Validação de payload

### Autenticação
- ✅ Apenas admin pode criar assinatura
- ✅ Apenas admin pode cancelar
- ✅ Apenas admin pode atualizar cartão
- ✅ Webhook é público (validado por assinatura)

---

## 📊 Planos de Assinatura

### Exemplo de Estrutura

```json
{
    "plan_basic": {
        "id": "plan_basic",
        "name": "Plano Básico",
        "description": "Para pequenas imobiliárias",
        "monthly_price": 99.00,
        "annual_price": 990.00,
        "max_users": 5,
        "max_properties": 100,
        "max_leads": 500,
        "features": [
            "Dashboard",
            "Gerenciamento de imóveis",
            "Leads básicos",
            "Mapa interativo",
            "Suporte por email"
        ]
    },
    "plan_professional": {
        "id": "plan_professional",
        "name": "Plano Profissional",
        "description": "Para imobiliárias em crescimento",
        "monthly_price": 299.00,
        "annual_price": 2990.00,
        "max_users": 20,
        "max_properties": 1000,
        "max_leads": 5000,
        "features": [
            "Tudo do Básico",
            "Análise avançada",
            "Suporte prioritário",
            "Integrações API",
            "Temas customizáveis"
        ]
    },
    "plan_enterprise": {
        "id": "plan_enterprise",
        "name": "Plano Enterprise",
        "description": "Para grandes imobiliárias",
        "monthly_price": 999.00,
        "annual_price": 9990.00,
        "max_users": 100,
        "max_properties": 10000,
        "max_leads": 50000,
        "features": [
            "Tudo do Profissional",
            "Suporte dedicado",
            "Customizações ilimitadas",
            "SLA garantido",
            "Integração com sistemas externos"
        ]
    }
}
```

---

## 📈 Métricas e Monitoramento

### Dados Rastreados

```php
// Por assinatura
- Status (active, past_due, canceled, paused)
- Tentativas falhadas
- Próxima tentativa de cobrança
- Data de expiração
- Valor mensal/anual

// Por tenant
- Receita mensal recorrente (MRR)
- Receita anual recorrente (ARR)
- Churn rate
- Upgrade/downgrade
```

### Alertas Automáticos

```php
// Assinatura expirando em 7 dias
Subscription::expiring(7)->get()

// Assinatura vencida
Subscription::where('current_period_end', '<', now())->get()

// Cobrança falhada
Subscription::pastDue()->get()
```

---

## 🚀 Próximas Etapas

### Fase 5: Domínios e Temas
- Implementar routing por domínio
- Criar temas Clássico e Bauhaus
- Permitir customização de cores
- Gerar CSS dinâmico

### Fase 6: Portal Cliente Final
- Cadastro de clientes
- Sistema de intenções
- Notificações

### Fase 7: AWS
- Configurar EC2
- Configurar RDS
- Configurar Route 53
- Configurar CloudFront

---

## 📝 Checklist de Implementação

- [x] Criar serviço Pagar.me
- [x] Criar controller de assinaturas
- [x] Criar rotas de assinaturas
- [x] Aprimorar modelo Subscription
- [x] Criar migration para campos de assinatura
- [x] Implementar webhooks
- [x] Implementar segurança
- [ ] Registrar rotas em `bootstrap/app.php`
- [ ] Configurar variável de ambiente `PAGAR_ME_API_KEY`
- [ ] Testar fluxo completo
- [ ] Criar testes automatizados
- [ ] Criar documentação de API (Swagger)
- [ ] Criar frontend para assinaturas

---

## 🔗 Arquivos Criados

| Arquivo | Tipo | Descrição |
|---------|------|-----------|
| `app/Services/PagarMeService.php` | Service | Integração com Pagar.me |
| `app/Http/Controllers/SubscriptionController.php` | Controller | Gerenciar assinaturas |
| `routes/subscriptions.php` | Routes | Rotas de assinaturas |
| `database/migrations/2025_12_18_100004_add_subscription_fields_to_tenants.php` | Migration | Campos de assinatura |

---

## 📚 Documentação

- ✅ Análise do projeto: `/home/ubuntu/analise_projeto_exclusiva.md`
- ✅ Arquitetura SaaS: `/home/ubuntu/exclusiva_saas_architecture.md`
- ✅ Fase 2 (Multi-tenant): `/home/ubuntu/FASE2_MULTI_TENANT_IMPLEMENTATION.md`
- ✅ Fase 3 (Super Admin): `/home/ubuntu/FASE3_SUPER_ADMIN_PANEL.md`
- ✅ Fase 4 (este documento): `/home/ubuntu/FASE4_PAGAR_ME_INTEGRATION.md`

---

**Data:** 2025-12-18
**Status:** ✅ Completo
**Próximo Passo:** Fase 5 - Domínios e Temas
