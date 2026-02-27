# Socimob Ads Automation — Documentação Técnica

## Índice
1. [Visão Geral](#visão-geral)
2. [Configuração (ENV vars)](#configuração-env-vars)
3. [Fluxos OAuth](#fluxos-oauth)
4. [Modelagem de Dados](#modelagem-de-dados)
5. [Endpoints da API](#endpoints-da-api)
6. [Jobs e Crons](#jobs-e-crons)
7. [Como Testar Local / Homologação](#como-testar-local--homologação)
8. [Troubleshooting](#troubleshooting)
9. [Checklist de Produção](#checklist-de-produção)
10. [Exemplos cURL](#exemplos-curl)

---

## Visão Geral

O módulo **Ads Automation** permite ao tenant publicar imóveis em anúncios pagos (Meta/Google Ads) com 1 clique, captar leads automaticamente e inseri-los no CRM — sem intervenção manual.

### Arquitetura

```
Tenant → Toggle "Publicar" → AdsOrchestrationService → Jobs na fila
    ├─ UpsertListingToCatalogJob  → MetaAdapter → Graph API
    ├─ EnsureCampaignStructureJob → Meta: campanha + adset
    └─ EnsureWebhookSubscriptionsJob → Meta Webhook

Meta → POST /api/ads/webhooks/meta/receive
    └─ IngestAdsLeadJob → LeadIngestionService → CRM (Pessoa + Lead + Notificação)
```

### Providers suportados
| Provider | Status | OAuth | Catálogo | Lead Ads | Webhook |
|---|---|---|---|---|---|
| Meta (FB+IG) | ✅ MVP | Business Login | home_listing | Lead Forms | leadgen push |
| Google Ads | 🚧 Post-MVP | OAuth 2.0 | stub | stub | pull |

---

## Configuração (ENV vars)

Adicionar ao `.env` do backend:

```env
# ─── Criptografia de tokens em repouso ──────────────────
ADS_ENCRYPTION_KEY=uma-chave-longa-e-aleatoria-min-32-chars

# ─── Meta (Facebook / Instagram) ───────────────────────
META_APP_ID=1234567890
META_APP_SECRET=abcdef1234567890abcdef1234567890
# URL de callback cadastrada no Meta App Dashboard:
APP_URL=https://seudominio.com

# ─── Google Ads (post-MVP) ──────────────────────────────
GOOGLE_ADS_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_ADS_CLIENT_SECRET=xxx
GOOGLE_ADS_DEVELOPER_TOKEN=xxx
GOOGLE_ADS_MCC_CUSTOMER_ID=xxx  # Se usar MCC do Socimob
```

### Gerar ADS_ENCRYPTION_KEY segura

```bash
php -r "echo bin2hex(random_bytes(32));"
# ou
openssl rand -hex 32
```

---

## Fluxos OAuth

### Meta Business Login

```
1. Tenant clica "Conectar Meta" no painel /ads
2. Frontend: POST /api/ads/meta/connect/start
   → Backend cria state anti-CSRF e retorna oauth_url
3. Frontend abre janela popup com oauth_url
4. Usuário autoriza no Facebook
5. Meta redireciona para: GET /api/ads/meta/connect/callback?code=xxx&state=xxx
6. Backend troca code por short-lived token → long-lived token (60 dias)
7. Backend salva token criptografado em ads_connections
8. Backend atualiza status para CONNECTED
```

**Permissões requeridas:**
- `ads_management`
- `ads_read`
- `business_management`
- `leads_retrieval`
- `pages_manage_ads`
- `pages_read_engagement`

**Configurar no Meta App Dashboard:**
- Products → Facebook Login → Settings → Valid OAuth Redirect URIs:
  `https://seudominio.com/api/ads/meta/connect/callback`
- App Review: solicitar permissões para `leads_retrieval` e `ads_management`

### Google Ads OAuth (Post-MVP)

```
1. POST /api/ads/google/connect/start → retorna accounts.google.com URL
2. Usuário autoriza e Meta redireciona para /api/ads/google/connect/callback
3. Backend troca code por access_token + refresh_token
4. refresh_token salvo para renovação automática
```

---

## Modelagem de Dados

### Tabelas criadas pela migração `2026_02_26_000001_create_ads_automation_tables.php`

#### `ads_connections`
Armazena tokens OAuth por tenant/provider.

| Coluna | Tipo | Descrição |
|---|---|---|
| id | bigint | PK |
| tenant_id | bigint | FK → tenants |
| provider | varchar(20) | `meta` \| `google` |
| status | varchar(20) | `DRAFT` \| `CONNECTED` \| `READY` \| `ERROR` |
| token_enc | text | access_token cifrado (AES-256-GCM) |
| refresh_token_enc | text | refresh_token cifrado |
| scopes | json | permissões concedidas |
| expires_at | timestamp | expiração do token |
| external_user_id | varchar(100) | ID do usuário no provider |
| external_business_id | varchar(100) | Business/Portfolio ID (Meta) |
| metadata_json | json | dados adicionais |

#### `ads_listings`
Sincronização de imóveis com catálogos dos providers.

| Coluna | Tipo | Descrição |
|---|---|---|
| id | bigint | PK |
| tenant_id | bigint | FK → tenants |
| listing_id | bigint | FK → imo_properties |
| provider | varchar(20) | `meta` \| `google` |
| external_item_id | varchar(150) | ID no catálogo do provider |
| publish_status | varchar(20) | `DRAFT`\|`PUBLISHING`\|`ACTIVE`\|`PAUSED`\|`ERROR` |
| last_sync_at | timestamp | última sincronização bem-sucedida |
| last_error | text | mensagem do último erro |

#### `ads_leads`
Leads captados pelos providers com rastreamento de origem.

| Coluna | Tipo | Descrição |
|---|---|---|
| id | bigint | PK |
| tenant_id | bigint | FK → tenants |
| provider | varchar(20) | origem |
| external_lead_id | varchar(150) | ID do lead no provider |
| listing_id | bigint | imóvel relacionado |
| contact_id | bigint | Pessoa no CRM |
| crm_lead_id | bigint | Lead no CRM |
| external_campaign_id | varchar(100) | campanha |
| gclid | varchar(200) | Google Click ID |
| raw_payload_json | json | payload original (limpo após N dias — LGPD) |
| normalized_json | json | `{nome, email, telefone, mensagem}` |
| is_duplicate | boolean | deduplicado por email/telefone/24h |

#### `ads_audit_logs`
Rastreabilidade completa de todas as operações.

| Coluna | Tipo | Descrição |
|---|---|---|
| id | bigint | PK |
| tenant_id | bigint | FK → tenants |
| provider | varchar(20) | provider envolvido |
| entity_type | varchar(50) | `connection`\|`listing`\|`campaign`\|`webhook`\|`lead` |
| action | varchar(80) | `PUBLISH_REQUESTED`, `CATALOG_UPSERT`, `LEAD_RECEIVED`, etc. |
| status | varchar(20) | `SUCCESS`\|`ERROR`\|`SKIPPED` |
| message | text | mensagem descritiva |
| payload_json_sanitized | json | nunca contém tokens ou secrets |

---

## Endpoints da API

### Conexão (autenticado)

```
POST   /api/ads/{provider}/connect/start
GET    /api/ads/{provider}/connect/callback
DELETE /api/ads/{provider}/connect
POST   /api/ads/{provider}/accounts
```

### Configurações e Status

```
GET    /api/ads/status
POST   /api/ads/settings
GET    /api/ads/logs?provider=meta&status=error&per_page=50
```

### Publicação de imóvel

```
POST   /api/listings/{id}/ads/publish     { "provider": "meta" }
POST   /api/listings/{id}/ads/unpublish   { "provider": "meta" }
GET    /api/listings/{id}/ads/status
```

### Leads e estatísticas

```
GET    /api/ads/leads?provider=meta&listing_id=&date_from=&date_to=&per_page=30
GET    /api/ads/leads/stats
```

### Webhook (público, sem Auth)

```
GET    /api/ads/webhooks/{provider}/receive   ← verificação Meta (hub.challenge)
POST   /api/ads/webhooks/{provider}/receive   ← receber eventos (leadgen)
```

---

## Jobs e Crons

### Jobs da fila

| Job | Fila | Tentativas | Timeout |
|---|---|---|---|
| `UpsertListingToCatalogJob` | `ads-high` | 3 | 120s |
| `EnsureCampaignStructureJob` | `ads-high` | 3 | 120s |
| `EnsureWebhookSubscriptionsJob` | `ads-normal` | 3 | 60s |
| `PauseListingInCatalogJob` | `ads-high` | 3 | 60s |
| `IngestAdsLeadJob` | `ads-high` | 3 | 60s |
| `ReconcileAdsStatusJob` | `default` | 1 | 600s |

### Crons (registrados em `app/Console/Kernel.php`)

| Comando | Frequência | Função |
|---|---|---|
| `ads:reconcile` | A cada 15 min | Corrigir status stuck e tokens expirados |
| `ads:refresh-tokens` | A cada hora | Renovar tokens próximos de expirar |
| `ads:backfill-leads --provider=google` | A cada 6 horas | Pull de leads do Google (post-MVP) |
| `ads:cleanup-leads` | Diariamente | LGPD: anonimizar payloads antigos |

---

## Como Testar Local / Homologação

### 1. Pré-requisitos

```bash
# .env com as vars obrigatórias
ADS_ENCRYPTION_KEY=gere-com-openssl-rand-hex-32
META_APP_ID=seu_app_id
META_APP_SECRET=seu_app_secret
APP_URL=https://sua-url-com-ssl.ngrok.io
```

### 2. Rodar worker da fila

```bash
php artisan queue:work --queue=ads-high,ads-normal,default --sleep=1 --tries=3
```

### 3. Rodar scheduler

```bash
php artisan schedule:run
# ou para debug:
php artisan schedule:list
```

### 4. Testar webhook Meta com ngrok

```bash
ngrok http 8000
# Copiar URL https e configurar no Meta App Dashboard
# Também atualizar APP_URL no .env
```

### 5. Criar entitlement para tenant de teste

```bash
php artisan tinker
```
```php
$e = new \App\Models\Ads\AdsEntitlement;
$e->tenant_id = 1; // ID do tenant
$e->plan_code = 'ADS_BASIC';
$e->providers_allowed = ['meta'];
$e->max_listings_per_day = 50;
$e->max_budget_daily_cents = 10000;
$e->is_active = true;
$e->save();
```

### 6. Fluxo de teste completo

```
1. Acesse /ads no frontend
2. Clique "Conectar" → Meta
3. Autorize o app no Facebook
4. Salve a Ad Account ID (buscar em Meta Business Suite)
5. Cadastre um imóvel com título, preço, endereço e foto
6. Na tela do imóvel (step Revisão) → ative o toggle "Publicar anúncio"
7. Observe o status mudar para PUBLISHING → ACTIVE
8. Simule um lead via Meta Graph API Explorer:
   POST /{leadgen_form_id}/simulate_lead
9. Verifique que o lead aparece em:
   - GET /api/ads/leads
   - Página de Leads do CRM (badge "Meta Ads")
   - Notificação in-app
```

---

## Troubleshooting

### Token expirado / erro de autenticação

```
Sintoma: ads_connection.status = ERROR, last_error contém "OAuthException"
Solução:
  1. Tenant reconecta via /ads → "Conectar Meta"
  2. Ou admin executa: php artisan ads:refresh-tokens --provider=meta
```

### Webhook inválido (Meta retorna 401)

```
Sintoma: Meta não consegue verificar o endpoint
Checklist:
  1. APP_URL correto e com SSL (https)
  2. /api/ads/webhooks/meta/receive não está bloqueada por middleware de auth
  3. META_APP_SECRET correto (verifica assinatura HMAC)
  4. ads_webhooks.verify_token_enc foi salvo corretamente
```

### Imóvel preso em PUBLISHING

```
Sintoma: publish_status = PUBLISHING há mais de 30 minutos
Solução:
  1. php artisan ads:reconcile → detecta e marca ERROR
  2. Verificar logs: GET /api/ads/logs?status=error
  3. Tenant pode reativar o toggle para reenviar
```

### Rate limit da API Meta

```
Sintoma: HTTP 429 nos logs de auditoria
Solução:
  - Jobs têm backoff exponencial (30s, 2min, 5min)
  - Verificar se ads_listings.sync_attempts está crescendo
  - Se necessário, reduzir frequência do cron ads:reconcile
```

### Permissões negadas no Meta App Review

```
Pré-requisito de produção:
  - Solicitar "leads_retrieval" no App Review do Meta
  - Solicitar "ads_management" com caso de uso "Gerenciar campanhas para clientes"
  - Verificar special_ad_categories = HOUSING obrigatório para anúncios de imóveis
  - Testar com conta de desenvolvedor antes do review
```

---

## Checklist de Produção

### Meta

- [ ] Domínios de callback registrados no Meta App Dashboard (`Valid OAuth Redirect URIs`)
- [ ] SSL ativo na URL de callback (`https://`)
- [ ] Webhook URL configurado: `https://seudominio.com/api/ads/webhooks/meta/receive`
- [ ] Campos do webhook: `leadgen`
- [ ] App Review aprovado para `leads_retrieval` e `ads_management`
- [ ] `META_APP_SECRET` configurado no servidor (não no frontend!)
- [ ] `ADS_ENCRYPTION_KEY` gerada e salva em local seguro (não versionar no git)

### Fila e Workers

- [ ] Worker rodando com supervisord ou similar
- [ ] Tabela `jobs` e `failed_jobs` criadas (`php artisan queue:table && php artisan migrate`)
- [ ] Queues `ads-high`, `ads-normal` configuradas (`QUEUE_CONNECTION=database`)
- [ ] Dead-letter: monitorar `failed_jobs` regularmente

### LGPD

- [ ] `ads:cleanup-leads` rodando diariamente
- [ ] Configurar `--leads-days` conforme política de retenção
- [ ] Informar Titular: leads de anúncios têm rastreamento de origem

### Segurança

- [ ] `ADS_ENCRYPTION_KEY` em variável de ambiente segura (não no código)
- [ ] Rota webhook pública: proteger com rate limiting (nginx/firewall)
- [ ] Logs não contêm tokens (verificar `ads_audit_logs.payload_json_sanitized`)
- [ ] Endpoint `/api/ads/webhooks/meta/receive` adicionado à whitelist do ResolveTenant

---

## Exemplos cURL

### Iniciar conexão Meta

```bash
curl -X POST https://seudominio.com/api/ads/meta/connect/start \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-Domain: seudominio.com" \
  -H "Content-Type: application/json"
# Retorna: { "oauth_url": "https://facebook.com/..." }
```

### Publicar imóvel

```bash
curl -X POST https://seudominio.com/api/listings/42/ads/publish \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-Domain: seudominio.com" \
  -H "Content-Type: application/json" \
  -d '{"provider": "meta"}'
# Retorna: { "status": "publishing", "request_id": "uuid" }
```

### Verificar status do anúncio

```bash
curl https://seudominio.com/api/listings/42/ads/status \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-Domain: seudominio.com"
# Retorna: { "data": [{ "provider": "meta", "publish_status": "ACTIVE", ... }] }
```

### Consultar leads dos anúncios

```bash
curl "https://seudominio.com/api/ads/leads?provider=meta&per_page=20" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-Domain: seudominio.com"
```

### Verificar logs de auditoria

```bash
curl "https://seudominio.com/api/ads/logs?status=error&per_page=10" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-Domain: seudominio.com"
```

### Simular webhook Meta (para testes)

```bash
# Calcular assinatura HMAC-SHA256
PAYLOAD='{"object":"page","entry":[{"id":"PAGE_ID","changes":[{"field":"leadgen","value":{"leadgen_id":"LEAD_ID","form_id":"FORM_ID","ad_id":"AD_ID","adset_id":"ADSET_ID","campaign_id":"CAMPAIGN_ID","page_id":"PAGE_ID"}}]}]}'
SIG="sha256=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$META_APP_SECRET" | awk '{print $2}')"

curl -X POST https://seudominio.com/api/ads/webhooks/meta/receive \
  -H "Content-Type: application/json" \
  -H "X-Hub-Signature-256: $SIG" \
  -d "$PAYLOAD"
# Retorna: OK (200)
```

### Executar reconciliação manual

```bash
php artisan ads:reconcile --tenant-id=1 --provider=meta
```

### Executar cleanup LGPD em dry-run

```bash
php artisan ads:cleanup-leads --dry-run
# Mostra o que seria removido sem executar
```
