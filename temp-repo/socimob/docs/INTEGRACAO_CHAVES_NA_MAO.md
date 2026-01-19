# Integração Chaves na Mão - Documentação

## ⚠️ IMPORTANTE: Fluxo de Integração

**WEBHOOK (RECEBEMOS LEADS)**

O Chaves na Mão **envia leads PARA NÓS** via webhook, não o contrário!

### Fluxo Correto:
1. 🌐 Portais de imóveis/veículos → Chaves na Mão
2. 📤 Chaves na Mão → **NOSSO WEBHOOK**
3. 💾 Salvamos lead no banco de dados
4. ✅ Respondemos com sucesso 200

---

## Configuração no Chaves na Mão

### URL do Webhook

Forneça esta URL no painel do Chaves na Mão:

```
https://lojadaesquina.store/webhook/chaves-na-mao
```

### Credenciais de Autenticação

O Chaves na Mão enviará estas credenciais via Basic Auth:

```
Email: contato@exclusivarlarimoveis.com
Token: d825c542e26df27c9fe696c391ee590
```

**Formato do Header:**
```
Authorization: Basic Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkw
```

### 🔧 Passos para Configurar no Painel Chaves na Mão

1. **Acessar Painel Administrativo**
   - Faça login no painel do Chaves na Mão
   - Navegue até: Configurações → Integrações → Webhooks

2. **Cadastrar URL do Webhook**
   - Cole a URL: `https://lojadaesquina.store/webhook/chaves-na-mao`
   - Método: POST
   - Content-Type: application/json

3. **Configurar Autenticação**
   - Tipo: HTTP Basic Authentication
   - Usuário: `contato@exclusivarlarimoveis.com`
   - Senha: `d825c542e26df27c9fe696c391ee590`

4. **Selecionar Eventos**
   - ✅ Novo Lead Criado
   - ✅ Segmento: REAL_ESTATE (Imóveis)
   - ✅ Segmento: VEHICLE (Veículos)

5. **Testar Integração**
   - Use o botão "Enviar Teste" no painel
   - Verifique se o lead aparece no banco de dados
   - Consulte os logs: `storage/logs/lumen-YYYY-MM-DD.log`

6. **Ativar Webhook**
   - Marque como "Ativo"
   - Salve as configurações
   - Webhook começa a receber leads em tempo real

---

## Arquitetura

### Componentes

1. **ChavesNaMaoWebhookController** (`app/Http/Controllers/ChavesNaMaoWebhookController.php`)
   - Recebe requisições POST do Chaves na Mão
   - Valida autenticação Basic Auth
   - Processa e salva leads

2. **Rota Webhook** (`routes/web.php`)
   - `POST /webhook/chaves-na-mao`
   - Pública (sem middleware de auth do sistema)
   - Valida autenticação internamente

---

## Formato dos Dados Recebidos

### Lead de Imóvel

```json
{
  "id": "12345",
  "name": "João Silva",
  "email": "joao@email.com",
  "phone": "11999999999",
  "message": "Tenho interesse no imóvel",
  "segment": "REAL_ESTATE",
  "ad": {
    "id": "67890",
    "title": "Apartamento 3 quartos",
    "type": "Apartamento",
    "purpose": "Venda",
    "reference": "REF001",
    "rooms": 3,
    "suites": 1,
    "garages": 2,
    "price": 450000,
    "neighborhood": "Pampulha",
    "city": "Belo Horizonte",
    "state": "MG"
  }
}
```

### Lead de Veículo

```json
{
  "id": "54321",
  "name": "Maria Santos",
  "email": "maria@email.com",
  "phone": "31988776655",
  "message": "Quero fazer test drive",
  "segment": "VEHICLE",
  "ad": {
    "id": "98765",
    "title": "Honda Civic 2020",
    "brand": "Honda",
    "model": "Civic",
    "year": 2020,
    "price": 85000
  }
}
```

---

## Processamento do Lead

### Mapeamento de Campos

| Chaves na Mão | Campo no Banco | Observações |
|---------------|----------------|-------------|
| `name` | `nome` | Obrigatório |
| `email` | `email` | Opcional |
| `phone` | `telefone` | Opcional |
| `ad.rooms` | `quartos` | Apenas imóveis |
| `ad.suites` | `suites` | Apenas imóveis |
| `ad.garages` | `garagem` | Apenas imóveis |
| `ad.price` | `budget_max` | Convertido para float |
| `ad.neighborhood + city` | `localizacao` | Concatenado |
| `message + ad data` | `observacoes` | Texto formatado |

### Status Inicial

Todos os leads recebidos são criados com `status = 'novo'`

---

## Testes

### Testar Webhook Localmente

```bash
curl -X POST http://localhost:8000/webhook/chaves-na-mao \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkw" \
  -d '{
    "id": "TEST001",
    "name": "Lead de Teste",
    "email": "teste@example.com",
    "phone": "31999999999",
    "message": "Teste de integração",
    "segment": "REAL_ESTATE",
    "ad": {
      "id": "AD001",
      "title": "Apartamento Teste",
      "type": "Apartamento",
      "purpose": "Venda",
      "rooms": 3,
      "price": 300000
    }
  }'
```

**Resposta esperada (200):**
```json
{
  "success": true,
  "message": "Lead recebido e processado",
  "lead_id": 123
}
```

### Testar em Produção

```bash
curl -X POST https://lojadaesquina.store/webhook/chaves-na-mao \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkw" \
  -d '{
    "id": "PROD_TEST",
    "name": "Teste Produção",
    "phone": "31987654321",
    "segment": "REAL_ESTATE"
  }'
```

---

## Monitoramento

### Logs

Todos os webhooks recebidos são logados em `storage/logs/lumen-YYYY-MM-DD.log`:

- ✅ `📥 Lead recebido do Chaves na Mão`
- ✅ `✅ Lead processado com sucesso`
- ⚠️ `⚠️ Webhook sem autenticação`
- 🔒 `🔒 Tentativa de acesso não autorizada`
- ❌ `❌ Erro ao processar lead`

### Queries Úteis

**Leads recebidos hoje:**
```sql
SELECT id, nome, telefone, email, created_at, observacoes
FROM leads 
WHERE observacoes LIKE '%Chaves na Mão%'
AND DATE(created_at) = CURDATE()
ORDER BY created_at DESC;
```

**Últimos 10 leads do Chaves na Mão:**
```sql
SELECT id, nome, telefone, status, created_at
FROM leads
WHERE observacoes LIKE '%Origem: Chaves na Mão%'
ORDER BY created_at DESC
LIMIT 10;
```

---

## Segurança

- ✅ Autenticação Basic Auth obrigatória
- ✅ Validação de credenciais via .env
- ✅ Logs de tentativas não autorizadas
- ✅ Resposta 401 para credenciais inválidas
- ✅ Tenant ID fixo (Exclusiva = 1)

---

## Troubleshooting

### Webhook não recebe leads

1. **Verificar URL configurada no Chaves na Mão**
   - URL correta: `https://lojadaesquina.store/webhook/chaves-na-mao`
   - Método: POST
   - Content-Type: application/json

2. **Verificar logs**
   ```bash
   tail -f storage/logs/lumen-$(date +%Y-%m-%d).log | grep "Chaves"
   ```

3. **Testar endpoint manualmente** (ver seção Testes acima)

### Erro 401

- Verificar se credenciais no Chaves na Mão estão corretas
- Email: `contato@exclusivalarimoveis.com.br`
- Token: `d825c542e26df27c9fe696c391ee590`

### Lead não aparece no sistema

1. Verificar logs para erros de processamento
2. Verificar se `tenant_id = 1` está correto
3. Consultar banco diretamente:
   ```sql
   SELECT * FROM leads ORDER BY created_at DESC LIMIT 1;
   ```

---

## Configuração no Painel Chaves na Mão

1. Acesse o painel administrativo do Chaves na Mão
2. Vá em **Configurações** → **Webhooks** (ou similar)
3. Configure:
   - **URL Webhook**: `https://lojadaesquina.store/webhook/chaves-na-mao`
   - **Método**: POST
   - **Autenticação**: Basic Auth
   - **Email**: `contato@exclusivalarimoveis.com.br`
   - **Token**: `d825c542e26df27c9fe696c391ee590`
4. Salve e teste o envio

---

## Próximos Passos

- [ ] Configurar webhook no painel do Chaves na Mão
- [ ] Fazer teste de envio via painel
- [ ] Monitorar logs por 24h
- [ ] Configurar notificações de novos leads (opcional)
- [ ] Criar dashboard de leads recebidos (opcional)


## Arquitetura

### Componentes

1. **ChavesNaMaoService** (`app/Services/ChavesNaMaoService.php`)
   - Comunicação com API externa
   - Autenticação via HTTP Basic Auth
   - Tratamento de erros e retry com backoff exponencial

2. **LeadObserver** (`app/Observers/LeadObserver.php`)
   - Detecta criação/atualização de leads
   - Dispara envio automático

3. **ChavesNaMaoController** (`app/Http/Controllers/ChavesNaMaoController.php`)
   - Endpoints HTTP para testes e monitoramento

4. **ChavesNaMaoCommand** (`app/Console/Commands/ChavesNaMaoCommand.php`)
   - Comandos CLI para gestão da integração

## Configuração

### Variáveis de Ambiente

Adicione ao `.env.production`:

```env
EXCLUSIVA_MAIL_CHAVES_NA_MAO=contato@exclusivalarimoveis.com.br
EXCLUSIVA_CHAVES_NA_MAO=d825c542e26df27c9fe696c391ee590
```

### Migration

Execute a migration para adicionar campos de controle:

```bash
php artisan migrate --path=database/migrations/2025_12_26_010500_add_chaves_na_mao_integration_to_leads.php
```

Campos adicionados à tabela `leads`:
- `chaves_na_mao_status` - pending|sent|error
- `chaves_na_mao_sent_at` - timestamp do envio bem-sucedido
- `chaves_na_mao_response` - resposta da API
- `chaves_na_mao_error` - mensagem de erro (se houver)
- `chaves_na_mao_retries` - contador de tentativas

## Funcionamento

### Envio Automático

1. **Lead criado**: Observer detecta e envia automaticamente
2. **Lead atualizado**: Se não foi enviado ainda, tenta enviar
3. **Idempotência**: Leads já enviados não são reenviados

### Validação

Leads precisam ter:
- ✅ Nome (obrigatório)
- ✅ Email OU Telefone (pelo menos um)

### Payload Enviado

```json
{
  "nome": "Nome do Lead",
  "email": "email@example.com",
  "telefone": "31999999999",
  "origem": "Exclusiva SaaS",
  "status": "novo",
  "observacoes": "...",
  "orcamento": "R$ 300.000,00 - R$ 500.000,00",
  "localizacao": "Pampulha, Belo Horizonte",
  "quartos": 3,
  "referencia_externa": "EXCLUSIVA_LEAD_123"
}
```

### Tratamento de Erros

| Código | Tipo | Ação |
|--------|------|------|
| 401/403 | Autenticação | ❌ Bloqueia e alerta |
| 4xx | Payload inválido | ⚠️ Registra e não retenta automaticamente |
| 5xx | Erro do servidor | 🔄 Retry com backoff (1min, 5min, 30min) |

## Endpoints HTTP

### Status da Integração

```http
GET /api/admin/chaves-na-mao/status
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "success": true,
  "stats": {
    "pending": 5,
    "sent": 120,
    "error": 3,
    "not_sent": 10
  },
  "last_errors": [...],
  "last_sent": [...]
}
```

### Testar Integração

```http
POST /api/admin/chaves-na-mao/test
Authorization: Bearer {token}
Content-Type: application/json

{
  "lead_id": 123  // Opcional, usa primeiro disponível se omitido
}
```

### Retry de Leads Falhados

```http
POST /api/admin/chaves-na-mao/retry
Authorization: Bearer {token}
```

### Reenviar Lead Específico

```http
POST /api/admin/chaves-na-mao/resend
Authorization: Bearer {token}
Content-Type: application/json

{
  "lead_id": 123
}
```

## Comandos CLI

### Status

```bash
php artisan chaves:sync status
```

Exibe estatísticas e últimos erros.

### Testar

```bash
php artisan chaves:sync test
```

Envia primeiro lead disponível para teste.

### Retry

```bash
php artisan chaves:sync retry
```

Processa leads com erro, respeitando backoff.

## Monitoramento

### Logs

Todos os eventos são logados em `storage/logs/lumen-YYYY-MM-DD.log`:

- ✅ `🆕 Novo lead criado`
- ✅ `📤 Enviando lead para Chaves na Mão`
- ✅ `📥 Resposta da API`
- ⚠️ `⚠️ Falha ao enviar lead`
- ❌ `❌ Erro ao enviar lead`
- 🔒 `🔒 Erro de autenticação`

### Queries Úteis

**Leads não enviados:**
```sql
SELECT * FROM leads 
WHERE chaves_na_mao_status IS NULL 
AND email IS NOT NULL;
```

**Leads com erro:**
```sql
SELECT id, nome, chaves_na_mao_error, chaves_na_mao_retries, updated_at
FROM leads 
WHERE chaves_na_mao_status = 'error'
ORDER BY updated_at DESC;
```

**Taxa de sucesso:**
```sql
SELECT 
  chaves_na_mao_status,
  COUNT(*) as total,
  ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM leads), 2) as percentual
FROM leads
GROUP BY chaves_na_mao_status;
```

## Segurança

- ✅ Token nunca exposto em logs
- ✅ HTTPS obrigatório
- ✅ Basic Auth via base64(email:token)
- ✅ Credenciais apenas em .env

## Troubleshooting

### Lead não está sendo enviado

1. Verificar logs: `tail -f storage/logs/lumen-YYYY-MM-DD.log`
2. Verificar Observer registrado: `bootstrap/app.php`
3. Verificar credenciais: `.env.production`
4. Testar manualmente: `POST /api/admin/chaves-na-mao/test`

### Erro 401 - Autenticação

1. Confirmar credenciais no `.env.production`
2. Verificar se email/token estão corretos
3. Testar com curl:
   ```bash
   echo -n "email:token" | base64
   curl -H "Authorization: Basic <base64>" https://api.chavesnamao.com.br/leads
   ```

### Leads duplicados

- Sistema possui idempotência via campo `chaves_na_mao_sent_at`
- Para reenviar: usar endpoint `/resend` que reseta o status

## Próximos Passos

- [ ] Implementar webhook reverso (Chaves na Mão → Exclusiva)
- [ ] Dashboard visual de integração
- [ ] Notificações automáticas de erro
- [ ] Sincronização bidirecional de status
