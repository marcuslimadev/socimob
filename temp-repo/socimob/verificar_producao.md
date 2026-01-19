# 🔍 Verificar Produção - Webhook WhatsApp

## 1️⃣ Verificar Tenant em Produção

```bash
# SSH no servidor
ssh -p 65002 u815655858@145.223.105.168

# Verificar tenant
mysql -u u815655858_saas -p'MundoMelhor@10' u815655858_saas -e "
SELECT id, name, whatsapp_number, domain, is_active 
FROM tenants 
LIMIT 5;
"
```

**Esperado:** Deve ter um tenant com `whatsapp_number` configurado (o número do Twilio que recebe mensagens).

Se NÃO tiver, configurar:
```sql
UPDATE tenants 
SET whatsapp_number = '+551140405050'  -- Seu número Twilio
WHERE id = 1;
```

---

## 2️⃣ Verificar Logs em Produção (Tempo Real)

```bash
# Logs do webhook
cd domains/lojadaesquina.store/public_html
tail -f storage/logs/lumen-$(date +%Y-%m-%d).log | grep -E "(WEBHOOK|LEAD CRIADO|Lead criado)"
```

---

## 3️⃣ Testar Webhook (Enviar Mensagem WhatsApp)

Envie uma mensagem para o número do Twilio:
```
"Olá! Estou interessado em imóveis de 3 quartos na região central."
```

Deve aparecer nos logs:
```
✅ Lead criado e vinculado à conversa {"lead_id":X,"conversa_id":Y,"user_id":Z}
```

---

## 4️⃣ Verificar Lead no Banco

```sql
-- Último lead criado
mysql -u u815655858_saas -p'MundoMelhor@10' u815655858_saas -e "
SELECT 
    l.id,
    l.nome,
    l.telefone,
    l.status,
    l.tenant_id,
    l.user_id,
    l.created_at,
    u.name as cliente_nome,
    u.email as cliente_email
FROM leads l
LEFT JOIN users u ON l.user_id = u.id
ORDER BY l.id DESC 
LIMIT 5;
"
```

**Esperado:**
- `tenant_id` preenchido
- `user_id` preenchido (cliente criado automaticamente)

---

## 5️⃣ Verificar Dashboard TV

Abra no navegador:
```
https://lojadaesquina.store/app/dashboard-leads-tv.html
```

**Esperado:** Lead deve aparecer em até 2 segundos automaticamente.

---

## 🐛 Se Ainda Não Funcionar

### Problema: Tenant ID continua NULL

**Solução 1:** Configurar whatsapp_number no tenant
```sql
UPDATE tenants 
SET whatsapp_number = '+551140405050'  -- Número que RECEBE (TO do Twilio)
WHERE id = 1;
```

**Solução 2:** Forçar tenant padrão
Adicione no `.env` de produção:
```env
DEFAULT_TENANT_ID=1
```

E edite `WebhookController.php` linha ~82:
```php
private function resolveTenantForWebhook(Request $request, array $normalizedData): ?Tenant
{
    // Forçar tenant padrão se configurado
    $defaultTenantId = env('DEFAULT_TENANT_ID');
    if ($defaultTenantId) {
        return Tenant::find($defaultTenantId);
    }
    
    // Resto do código...
}
```

---

## 📊 Checklist Completo

- [ ] Fazer deploy da correção (FEITO ✅)
- [ ] Verificar tenant configurado em produção
- [ ] Configurar `whatsapp_number` no tenant
- [ ] Enviar mensagem teste
- [ ] Verificar logs: "Lead criado"
- [ ] Verificar banco: lead + cliente criados
- [ ] Abrir Dashboard TV: lead aparece automaticamente

---

## 🎯 Resultado Esperado

Quando enviar mensagem WhatsApp em produção:

1. **Webhook recebe** ✅ (já funcionava)
2. **Conversa criada/atualizada** ✅ (já funcionava)
3. **Lead criado** ✅ (CORRIGIDO agora)
4. **Cliente criado** ✅ (automático via ensureClientForLead)
5. **Dashboard TV atualiza** ✅ (automático a cada 2s)

🚀 **Tudo funcionando em sequência!**
