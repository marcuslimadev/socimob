-- Configurar tenant Exclusiva Imóveis
-- Banco: u815655858_saas
-- IMPORTANTE: Substituir os valores XXX pelas credenciais reais do .env

-- 1. Verificar/Criar tenant
INSERT INTO tenants (id, nome, slug, dominio, email, ativo, created_at, updated_at)
VALUES (1, 'Exclusiva Imóveis', 'alexsandra-fialho', 'exclusivalarimoveis.com', 'contato@exclusivalarimoveis.com.br', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    dominio = 'exclusivalarimoveis.com',
    email = 'contato@exclusivalarimoveis.com.br',
    updated_at = NOW();

-- 2. Configurar tenant_configs
-- Substituir XXX pelos valores do .env:
-- - EXCLUSIVA_TWILIO_ACCOUNT_SID
-- - EXCLUSIVA_TWILIO_AUTH_TOKEN
-- - EXCLUSIVA_OPENAI_API_KEY
INSERT INTO tenant_configs (
    tenant_id,
    twilio_account_sid,
    twilio_auth_token,
    twilio_whatsapp_from,
    whatsapp_number,
    api_key_openai,
    openai_model,
    ai_assistant_name,
    smtp_host,
    smtp_port,
    smtp_username,
    smtp_password,
    smtp_from_email,
    smtp_from_name,
    notify_new_leads,
    notify_new_properties,
    notify_new_messages,
    notification_email,
    primary_color,
    secondary_color,
    accent_color,
    max_images_per_property,
    max_properties,
    max_leads,
    created_at,
    updated_at
) VALUES (
    1, -- tenant_id
    'XXX_TWILIO_ACCOUNT_SID', -- substituir pelo valor do .env
    'XXX_TWILIO_AUTH_TOKEN', -- substituir pelo valor do .env
    'whatsapp:+553173341150',
    '+553173341150',
    'XXX_OPENAI_API_KEY', -- substituir pelo valor do .env
    'gpt-4o-mini',
    'Teresa',
    'smtp.titan.email',
    587,
    'alert@socimob.com',
    'MundoMelhor@10',
    'alert@socimob.com',
    'SOCIMOB',
    1, -- notify_new_leads
    1, -- notify_new_properties
    1, -- notify_new_messages
    'contato@exclusivalarimoveis.com.br',
    '#1e40af',
    '#64748b',
    '#f59e0b',
    20,
    5000,
    10000,
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    twilio_account_sid = 'XXX_TWILIO_ACCOUNT_SID',
    twilio_auth_token = 'XXX_TWILIO_AUTH_TOKEN',
    twilio_whatsapp_from = 'whatsapp:+553173341150',
    whatsapp_number = '+553173341150',
    api_key_openai = 'XXX_OPENAI_API_KEY',
    openai_model = 'gpt-4o-mini',
    ai_assistant_name = 'Teresa',
    smtp_host = 'smtp.titan.email',
    smtp_port = 587,
    smtp_username = 'alert@socimob.com',
    smtp_password = 'MundoMelhor@10',
    smtp_from_email = 'alert@socimob.com',
    smtp_from_name = 'SOCIMOB',
    notification_email = 'contato@exclusivalarimoveis.com.br',
    updated_at = NOW();

-- 3. Verificar configuração
SELECT 
    tc.tenant_id,
    t.nome,
    tc.whatsapp_number,
    tc.twilio_whatsapp_from,
    tc.ai_assistant_name,
    tc.openai_model,
    tc.smtp_from_email,
    tc.notification_email
FROM tenant_configs tc
INNER JOIN tenants t ON t.id = tc.tenant_id
WHERE tc.tenant_id = 1;
