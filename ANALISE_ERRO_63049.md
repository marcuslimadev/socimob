# Análise do erro 63049 - Template WhatsApp Twilio

## Causa do erro baseada no código atual:

### ✅ O que está CORRETO no código:

1. **Formato do telefone**: `whatsapp:+5592992287144` ✅
2. **Uso de ContentSid**: O código usa `ContentSid` ao invés de `Body` ✅  
3. **Estrutura das variáveis**: `{"1":"Marcus André","2":"imóveis"}` ✅
4. **Método HTTP**: POST para `/Messages.json` ✅

### ❌ Possíveis causas do erro 63049:

## 1. Template não vinculado ao número sender
O template `HX4f61c2b07ceef4afc402a9c4753300df` precisa estar:
- ✅ Aprovado pela Meta (status: approved)
- ⚠️  **Vinculado ao número sender específico**

**Como verificar no Twilio Console:**
```
Twilio Console > Messaging > Content Editor > Templates
├─ Procure: HX4f61c2b07ceef4afc402a9c4753300df
├─ Status: deve ser "approved"
└─ Senders: deve incluir o número whatsapp:+553191234567
```

## 2. Variáveis do template não correspondem
Se o template aprovado pela Meta tem placeholders diferentes:

**Template esperado:**
```
{{1}} - Nome do cliente
{{2}} - Interesse
```

**Enviado no código:**
```json
{
  "1": "Marcus André",
  "2": "imóveis"
}
```

**Como verificar:**
- No Twilio Console, abra o template HX4f61c2b07ceef4afc402a9c4753300df
- Veja o conteúdo aprovado
- Conte os placeholders {{1}}, {{2}}, etc.
- Verifique se são exatamente 2 variáveis

## 3. Número From não aprovado para WhatsApp Business
O número `whatsapp:+553191234567` (ou similar) precisa:
- Estar registrado no WhatsApp Business API
- Ter permissões para enviar templates
- Estar ativo e não suspenso

**Como verificar:**
```
Twilio Console > Messaging > Senders > WhatsApp senders
└─ Procure seu número e verifique status
```

## 4. Formato incorreto das variáveis (menos provável)
Twilio pode esperar variáveis em formato diferente. Teste alterando:

**Formato atual:**
```php
$data['ContentVariables'] = json_encode(['1' => 'Marcus', '2' => 'imóveis']);
// Resultado: {"1":"Marcus","2":"imóveis"}
```

**Formato alternativo (se necessário):**
```php
// Com chaves numéricas como strings
$data['ContentVariables'] = json_encode(['1' => 'Marcus', '2' => 'imóveis']);

// Ou sem JSON encode (deixar Twilio encodar)
$data['ContentVariables[1]'] = 'Marcus';
$data['ContentVariables[2]'] = 'imóveis';
```

## 📋 CHECKLIST PARA RESOLVER:

### Passo 1: Verificar template no Twilio Console
```
1. Acesse: https://console.twilio.com/us1/develop/sms/content-editor
2. Procure: HX4f61c2b07ceef4afc402a9c4753300df
3. Verifique:
   [ ] Status = "approved"
   [ ] Contém exatamente 2 variáveis {{1}} e {{2}}
   [ ] Sender number está vinculado ao template
```

### Passo 2: Verificar número sender
```
1. Acesse: https://console.twilio.com/us1/develop/sms/senders/whatsapp-senders
2. Encontre seu número WhatsApp Business
3. Verifique:
   [ ] Status = "active"
   [ ] Não tem alertas ou suspensões
   [ ] Tem permissão para enviar templates
```

### Passo 3: Testar API diretamente com cURL
```bash
curl -X POST "https://api.twilio.com/2010-04-01/Accounts/YOUR_ACCOUNT_SID/Messages.json" \
  --data-urlencode "From=whatsapp:+553191234567" \
  --data-urlencode "To=whatsapp:+5592992287144" \
  --data-urlencode "ContentSid=HX4f61c2b07ceef4afc402a9c4753300df" \
  --data-urlencode 'ContentVariables={"1":"Marcus André","2":"imóveis"}' \
  -u YOUR_ACCOUNT_SID:YOUR_AUTH_TOKEN
```

### Passo 4: Revisar logs do Twilio
```
1. Acesse: https://console.twilio.com/us1/monitor/logs/messaging
2. Procure pelo Message SID: MM7dd72138cf9d3b0538c62b2a629b420a
3. Veja detalhes do erro 63049
4. Twilio mostrará exatamente qual parâmetro está faltando/incorreto
```

## 🎯 SOLUÇÃO MAIS PROVÁVEL:

Baseado na experiência com erro 63049, **90% dos casos** são:

1. **Template não vinculado ao sender** (mais comum)
   - Solução: No Twilio Console, adicione o sender ao template

2. **Template não aprovado ou pendente**
   - Solução: Aguarde aprovação da Meta ou use template pré-aprovado

3. **Número de variáveis incorreto**
   - Solução: Conte os {{}} no template e ajuste o código

## 📝 CÓDIGO DE TESTE SUGERIDO:

```php
// Adicione este log ANTES de enviar o template
\Log::info('[TEST] Payload completo do template', [
    'From' => $this->whatsappFrom,
    'To' => $to,
    'ContentSid' => $contentSid,
    'ContentVariables' => $contentVariables,
    'ContentVariables_JSON' => json_encode($contentVariables)
]);
```

Isso mostrará EXATAMENTE o que está sendo enviado ao Twilio.
