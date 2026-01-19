-- Script para criar/atualizar tenant Exclusiva Lar Imóveis
-- Execute este script no MySQL

-- Verificar se tenant já existe
SELECT id, name, domain FROM tenants WHERE name LIKE '%Exclusiva%' OR domain LIKE '%exclusiva%';

-- Se não existir, criar
INSERT INTO tenants (
    name, 
    domain, 
    slug, 
    theme, 
    primary_color, 
    secondary_color, 
    logo_url,
    description,
    contact_email, 
    contact_phone,
    subscription_status,
    subscription_plan,
    is_active,
    created_at,
    updated_at
) VALUES (
    'Exclusiva Lar Imóveis',
    'exclusivalarimoveis.com',
    'exclusiva-lar',
    'classico',
    '#1e293b',
    '#3b82f6',
    '/assets/logo-exclusiva.png',
    'Encontre o Imóvel dos Seus Sonhos',
    'contato@exclusivalarimoveis.com.br',
    '(31) 97559-7278',
    'active',
    'premium',
    1,
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    description = 'Encontre o Imóvel dos Seus Sonhos',
    contact_email = 'contato@exclusivalarimoveis.com.br',
    contact_phone = '(31) 97559-7278',
    primary_color = '#1e293b',
    secondary_color = '#3b82f6',
    logo_url = '/assets/logo-exclusiva.png',
    is_active = 1,
    updated_at = NOW();

-- Verificar resultado
SELECT * FROM tenants WHERE name = 'Exclusiva Lar Imóveis';
