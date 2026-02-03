# SMS com Link do WhatsApp para Leads do Chaves na Mão

## 📱 Funcionalidade

Quando um lead é recebido via webhook do **Chaves na Mão**, o sistema agora envia automaticamente um **SMS** para o telefone do lead contendo:

1. Mensagem de boas-vindas personalizada
2. Link direto para WhatsApp Web do tenant
3. Mensagem pré-preenchida com informações do imóvel

## 🔄 Fluxo de Funcionamento

```
Lead Chaves na Mão → Webhook → Sistema SOCIMOB → SMS Twilio → Lead
                                      ↓
                                 WhatsApp Link
                                      ↓
                            Conversa Direta com Tenant
```

### Exemplo de SMS Enviado

```
Olá João! 👋

Recebemos seu interesse no imóvel.

Clique aqui para falar direto conosco no WhatsApp:
https://wa.me/5531973341150?text=Ol%C3%A1!%20Sou%20Jo%C3%A3o%20e%20tenho%20interesse%20no%20im%C3%B3vel...
```

## ⚙️ Configuração

### 1. Executar Migration

```bash
php artisan migrate
```

Isso adiciona o campo `whatsapp_number` na tabela `tenant_configs`.

### 2. Configurar Número de WhatsApp do Tenant

Execute o script interativo:

```bash
php configure_whatsapp_number.php
```

Será solicitado:
- ID do tenant
- Número de WhatsApp (formato: +5531973341150)

### 3. Verificar Configuração

```sql
SELECT tenant_id, whatsapp_number 
FROM tenant_configs 
WHERE tenant_id = 1;
```

### 4. Configurar Twilio SMS

Certifique-se de que as variáveis de ambiente do Twilio estão configuradas:

```env
EXCLUSIVA_TWILIO_ACCOUNT_SID=AC...
EXCLUSIVA_TWILIO_AUTH_TOKEN=...
EXCLUSIVA_TWILIO_SMS_FROM=+1234567890
# ou
EXCLUSIVA_TWILIO_PHONE_NUMBER=+1234567890
```

## 📋 Campos Necessários

### Tabela: `tenant_configs`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `whatsapp_number` | VARCHAR(255) | Número de WhatsApp do tenant no formato internacional |

### Exemplo de Dados

```
tenant_id: 1
whatsapp_number: +5531973341150
```

## 🔧 Código Principal

### Controller: `ChavesNaMaoWebhookController`

**Método:** `sendWhatsAppSMS()`

- Valida telefone do lead
- Busca número de WhatsApp do tenant
- Cria link do WhatsApp Web com mensagem pré-preenchida
- Envia SMS via Twilio

**Service:** `TwilioService::sendSMS()`

- Normaliza número de telefone
- Envia SMS via API Twilio
- Registra logs de sucesso/erro

## 📊 Logs

O sistema registra todas as operações:

```
✅ SMS com link do WhatsApp enviado com sucesso
  - lead_id: 123
  - telefone: +5531987654321
  - message_sid: SM...

❌ Erro ao enviar SMS com link do WhatsApp
  - lead_id: 123
  - error: ...
```

## 🧪 Teste

### Simular Recebimento de Lead

```bash
curl -X POST https://lojadaesquina.store/webhook/chaves-na-mao \
  -H "Authorization: Basic [BASE64_CREDENTIALS]" \
  -H "Content-Type: application/json" \
  -d '{
    "lead": {
      "id": "12345",
      "name": "João Silva",
      "email": "joao@example.com",
      "phone": "+5531987654321",
      "message": "Tenho interesse no imóvel",
      "segment": "REAL_ESTATE",
      "ad": {
        "type": "Apartamento",
        "reference": "AP001",
        "neighborhood": "Centro",
        "city": "Belo Horizonte",
        "rooms": 3
      }
    }
  }'
```

### Validações

1. ✅ Lead criado no banco de dados
2. ✅ SMS enviado para o telefone do lead
3. ✅ Link do WhatsApp aponta para número do tenant
4. ✅ Mensagem pré-preenchida contém informações do imóvel

## ⚠️ Observações

### Quando o SMS NÃO é enviado:

- Lead sem telefone válido (00000000000)
- Tenant sem `whatsapp_number` configurado
- Twilio não configurado (credenciais inválidas)

### Formato do Link WhatsApp

```
https://wa.me/{número}?text={mensagem_encoded}
```

Exemplo:
```
https://wa.me/5531973341150?text=Ol%C3%A1!%20Sou%20Jo%C3%A3o%20e%20tenho%20interesse%20no%20im%C3%B3vel...
```

## 📞 Suporte

Em caso de problemas:

1. Verificar logs: `storage/logs/laravel.log`
2. Verificar SystemLog: `SELECT * FROM system_logs WHERE category = 'twilio' ORDER BY created_at DESC LIMIT 10`
3. Validar credenciais Twilio
4. Testar envio de SMS manualmente via Twilio Console

## 🚀 Próximas Melhorias

- [ ] Personalizar mensagem do SMS por tenant
- [ ] Adicionar métricas de conversão SMS → WhatsApp
- [ ] Implementar retry automático em caso de falha
- [ ] Dashboard de acompanhamento de SMSs enviados
