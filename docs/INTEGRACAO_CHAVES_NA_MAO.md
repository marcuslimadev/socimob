# Integração Chaves na Mão - Documentação

## Visão Geral

Integração automática de leads do SaaS Exclusiva para a API de Leads do Chaves na Mão.

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
