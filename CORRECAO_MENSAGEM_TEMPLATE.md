# ✅ CORREÇÃO: Mensagem WhatsApp Template

## Problema Identificado
As mensagens enviadas via template aprovado do Meta/WhatsApp estavam sendo registradas no banco de dados como apenas:
```
"Template inicial aprovado (Meta)"
```

Isso aparecia no chat, o que era confuso para os atendentes.

## Causa Raiz
- Templates aprovados no Meta/WhatsApp são enviados via Twilio usando apenas um `ContentSid` (ex: `HX4f61c2b...`)
- O conteúdo literal da mensagem não é retornado pela API do Twilio
- O código estava salvando um placeholder hardcoded ao invés da mensagem real

## Solução Implementada

### 1. Adiciona Campo na Tabela `tenants`
```sql
ALTER TABLE tenants 
ADD COLUMN whatsapp_template_message TEXT NULL
COMMENT 'Texto do template aprovado no WhatsApp/Meta com placeholders {{1}}, {{2}}, etc.'
AFTER api_key_openai;
```

### 2. Configuração do Template para Tenant 1
```sql
UPDATE tenants 
SET whatsapp_template_message = 'Olá {{1}}, tudo bem? Vi que você tem interesse em {{2}}. Sou a Teresa, assistente virtual da Exclusiva Imóveis. Como posso ajudar você a encontrar o imóvel ideal?'
WHERE id = 1;
```

### 3. Atualização do Código

**WhatsAppService.php:**
- Novo método `expandirMensagemTemplate()` que:
  - Busca a mensagem configurada no tenant
  - Substitui `{{1}}` pelo nome do lead
  - Substitui `{{2}}` pelo tipo de interesse
  - Retorna a mensagem expandida

**LeadAutomationService.php:**
- Atualizado `mensagemTemplateRegistro()` para:
  - Receber o Lead como parâmetro
  - Buscar a mensagem do tenant
  - Expandir as variáveis do template
  - Retornar a mensagem completa para registro

## Resultado

### Antes (Problema):
```
[Claudia] 10:40
Template inicial aprovado (Meta)
```

### Depois (Corrigido):
```
[Teresa - Exclusiva Imóveis] 10:40
Olá Claudia, tudo bem? Vi que você tem interesse em apartamentos. Sou a Teresa, assistente virtual da Exclusiva Imóveis. Como posso ajudar você a encontrar o imóvel ideal?
```

## Como Funciona

1. **Lead criado** (webhook ou manual)
2. **Sistema identifica** que deve usar template (origem Chaves na Mão)
3. **Obtém variáveis** do lead:
   - `{{1}}` = Nome do lead (ex: "Claudia")
   - `{{2}}` = Tipo de interesse (ex: "apartamentos")
4. **Envia via Twilio** com ContentSid + variáveis
5. **Registra no banco** a mensagem expandida:
   ```
   "Olá Claudia, tudo bem? Vi que você tem interesse em apartamentos..."
   ```
6. **Aparece no chat** a mensagem real ao invés do placeholder

## Configuração para Novos Tenants

Para cada tenant que usar templates do WhatsApp:

1. Aprovar o template no Meta Business Manager
2. Obter o `ContentSid` (ex: `HX4f61c2b...`)
3. Configurar no `.env`:
   ```env
   TENANT_X_TWILIO_TEMPLATE_WELCOME_SID=HX4f61c2b...
   ```
4. Atualizar o campo `whatsapp_template_message` no banco:
   ```sql
   UPDATE tenants 
   SET whatsapp_template_message = 'Texto do template com {{1}}, {{2}}...'
   WHERE id = X;
   ```

## Arquivos Alterados

- ✅ `database/migrations/2025_01_26_000001_add_whatsapp_template_message_to_tenants.php`
- ✅ `app/Services/WhatsAppService.php`
- ✅ `app/Services/LeadAutomationService.php`

## Deploy Realizado

```bash
git commit -m "fix: corrigir mensagem do template WhatsApp"
git push origin master
ssh servidor "cd public_html && git pull"
mysql "ALTER TABLE tenants ADD COLUMN whatsapp_template_message..."
mysql "UPDATE tenants SET whatsapp_template_message = '...'"
```

✅ **Status:** CORRIGIDO E EM PRODUÇÃO
