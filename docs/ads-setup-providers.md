# Configuração de Providers de Anúncios

## Meta Ads (Facebook / Instagram) — totalmente funcional

### 1. Criar o App no Meta

1. Acesse [developers.facebook.com](https://developers.facebook.com) → **Meus Apps → Criar App**
2. Tipo: **Business** (não "Consumidor")
3. Adicione o produto **Marketing API**
4. Adicione o produto **Facebook Login for Business**

---

### 2. Configurar OAuth Redirect URI

Em **Facebook Login for Business → Configurações → URIs de redirecionamento OAuth válidos**, adicione exatamente:

```
https://lojadaesquina.store/api/ads/meta/connect/callback
```

---

### 3. Configurar Webhook

Em **Products → Webhooks → Página**:

| Campo | Valor |
|-------|-------|
| URL de callback | `https://lojadaesquina.store/api/ads/webhooks/meta/receive` |
| Evento | `leadgen` |
| Verify Token | Gerado automaticamente pelo sistema na primeira conexão |

---

### 4. Permissões requeridas (App Review)

A App precisa ter as seguintes permissões aprovadas:

- `ads_management`
- `ads_read`
- `business_management`
- `leads_retrieval`
- `pages_manage_ads`
- `pages_read_engagement`

> **Nota:** Para testar com usuários de teste não é necessário App Review.  
> Para uso em produção (clientes reais) é necessário submeter para revisão no Meta.

---

### 5. Variáveis de ambiente no servidor

Adicionar ao `.env` do backend:

```env
META_APP_ID=seu_app_id_aqui
META_APP_SECRET=seu_app_secret_aqui
ADS_ENCRYPTION_KEY=uma-chave-aleatoria-de-32-chars-minimo
APP_URL=https://lojadaesquina.store
```

Gerar uma `ADS_ENCRYPTION_KEY` segura:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

---

### 6. Conta de Anúncios do cliente (Ad Account)

Após o cliente conectar via OAuth, é necessário cadastrar o **Ad Account ID** dele no banco de dados (`ads_accounts`). O ID fica em:

> **Gerenciador de Negócios → Contas de Anúncios**

Formato: `act_XXXXXXXXX`

O sistema usa essa conta para criar campanhas e adsets.

---

### 7. Fluxo de conexão (resumo)

```
1. Cliente acessa /ads → aba Conexões → "Conectar via OAuth"
2. Popup do Facebook abre com as permissões listadas acima
3. Cliente autoriza
4. Meta redireciona para: GET /api/ads/meta/connect/callback?code=xxx&state=xxx
5. Backend troca o code por short-lived token → long-lived token (válido 60 dias)
6. Token salvo criptografado no banco (ads_connections)
7. Status muda para CONNECTED
8. Renovação automática antes do vencimento via artisan ads:refresh-tokens
```

---

## Google Ads — não implementado (pós-MVP)

O `GoogleAdapter.php` existe como **stub completo** — a interface está definida mas os métodos de campanha/catálogo lançam `RuntimeException`.

### O que está pronto:
- Geração da URL OAuth (`accounts.google.com`)
- Estrutura de banco de dados (`ads_accounts`, `ads_connections`, etc.)
- Callback registrado em `routes/web.php`

### O que falta implementar:
- Instalar SDK oficial: `google/ads-googleads`
- Troca de `code` por `access_token` + `refresh_token` no callback
- Criação de campanhas com **Lead Form Assets**
- Ingestão de leads via **Reports API** (modelo pull, sem webhook)
- Remarketing com **Customer Match**

### Variáveis de ambiente necessárias (futuro):

```env
GOOGLE_ADS_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_ADS_CLIENT_SECRET=xxx
GOOGLE_ADS_DEVELOPER_TOKEN=xxx
GOOGLE_ADS_MCC_CUSTOMER_ID=xxx
```

---

## Troubleshooting Meta

### Token expirado / erro de autenticação

```
Sintoma: ads_connection.status = ERROR, log contém "OAuthException"
Solução:
  1. Cliente reconecta via /ads → "Conectar via OAuth"
  2. Ou admin executa: php artisan ads:refresh-tokens --provider=meta
```

### Webhook inválido (Meta retorna 401 / não verifica)

```
Checklist:
  1. APP_URL correto com SSL (https obrigatório)
  2. /api/ads/webhooks/meta/receive não está bloqueada por middleware de auth
  3. META_APP_SECRET correto (assina HMAC SHA-256)
  4. ads_webhooks.verify_token_enc foi salvo corretamente no banco
```

### Campanha não criada após publicar imóvel

```
Checklist:
  1. ads_accounts tem registro com tenant_id correto e is_active = 1
  2. external_account_id está preenchido (act_XXXXXXXXX)
  3. Verificar logs em /ads → aba Logs → filtrar por status Erro
```
