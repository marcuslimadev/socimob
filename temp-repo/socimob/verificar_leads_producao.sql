-- Verificar leads em produção
SELECT 
    id, 
    nome, 
    whatsapp_name,
    telefone, 
    email, 
    status, 
    tenant_id, 
    user_id, 
    origem,
    created_at 
FROM leads 
ORDER BY id DESC 
LIMIT 10;

-- Verificar conversas em produção
SELECT 
    id,
    telefone,
    whatsapp_name,
    lead_id,
    tenant_id,
    status,
    created_at
FROM conversas
ORDER BY id DESC
LIMIT 10;

-- Verificar se há conversas sem lead
SELECT 
    id,
    telefone,
    whatsapp_name,
    status,
    created_at
FROM conversas
WHERE lead_id IS NULL OR lead_id = 0
ORDER BY id DESC;
