-- ========================================
-- Configurar Tenant para WhatsApp Webhook
-- ========================================

-- 1. Verificar se tenant existe
SELECT id, name, domain, subdomain, whatsapp_number, is_active 
FROM tenants 
LIMIT 5;

-- 2. Se não existe nenhum tenant, criar:
INSERT INTO tenants (name, domain, subdomain, whatsapp_number, is_active, created_at, updated_at) 
VALUES (
    'Exclusiva Imóveis', 
    'exclusivalarimoveis.com.br', 
    NULL, 
    '+551140405050',  -- Substitua pelo seu número do Twilio
    1,
    NOW(),
    NOW()
);

-- 3. Se já existe, apenas atualizar o whatsapp_number:
UPDATE tenants 
SET whatsapp_number = '+551140405050',  -- Substitua pelo seu número do Twilio
    is_active = 1,
    updated_at = NOW()
WHERE id = 1;  -- Substitua pelo ID do seu tenant

-- 4. Verificar configuração:
SELECT 
    id, 
    name, 
    domain, 
    whatsapp_number,
    is_active
FROM tenants;

-- 5. (Opcional) Se quiser atualizar leads existentes sem tenant:
UPDATE leads 
SET tenant_id = 1  -- ID do tenant criado acima
WHERE tenant_id IS NULL;

-- 6. (Opcional) Se quiser atualizar conversas existentes sem tenant:
UPDATE conversas 
SET tenant_id = 1  -- ID do tenant criado acima
WHERE tenant_id IS NULL;

-- 7. Verificar quantos leads e conversas sem tenant ainda existem:
SELECT 
    (SELECT COUNT(*) FROM leads WHERE tenant_id IS NULL) as leads_sem_tenant,
    (SELECT COUNT(*) FROM conversas WHERE tenant_id IS NULL) as conversas_sem_tenant,
    (SELECT COUNT(*) FROM leads WHERE tenant_id = 1) as leads_com_tenant,
    (SELECT COUNT(*) FROM conversas WHERE tenant_id = 1) as conversas_com_tenant;
