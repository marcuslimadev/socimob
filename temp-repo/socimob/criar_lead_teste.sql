-- SQL para criar Lead de Teste simulando Chaves na Mão
-- Execute no MySQL: mysql -u root exclusiva < criar_lead_teste.sql
-- Ou copie e cole no PHPMyAdmin

-- 1. Verificar se tenant existe
SELECT @tenant_id := id FROM tenants WHERE slug = 'exclusiva' LIMIT 1;

-- 2. Se não existir, cria o tenant
INSERT IGNORE INTO tenants (nome, slug, dominio, is_active, created_at, updated_at)
VALUES ('Exclusiva Lar Imóveis', 'exclusiva', 'exclusiva.test', 1, NOW(), NOW());

-- 3. Pega o ID do tenant
SELECT @tenant_id := id FROM tenants WHERE slug = 'exclusiva' LIMIT 1;

-- 4. Criar o lead de teste
INSERT INTO leads (
    tenant_id,
    nome,
    email,
    telefone,
    status,
    origem,
    observacoes,
    tipo_imovel,
    tipo_negocio,
    valor_minimo,
    valor_maximo,
    quartos,
    banheiros,
    vagas_garagem,
    bairro,
    cidade,
    estado,
    created_at,
    updated_at
) VALUES (
    @tenant_id,
    'João Silva Teste IA',
    'joao.teste@email.com',
    '+5531987654321',
    'novo',
    'chavesnamao',
    'Lead recebido via integração Chaves na Mão\nTeste de atendimento automático IA\nInteresse: Apartamento 2 quartos para compra',
    'Apartamento',
    'compra',
    250000,
    400000,
    2,
    1,
    1,
    'Centro',
    'Belo Horizonte',
    'MG',
    NOW(),
    NOW()
);

-- 5. Mostrar resultado
SELECT 
    id,
    nome,
    telefone,
    status,
    origem,
    LEFT(observacoes, 50) as observacoes_preview,
    created_at
FROM leads 
WHERE email = 'joao.teste@email.com'
ORDER BY id DESC 
LIMIT 1;

-- Informações importantes:
-- ==================================================
-- ✅ Lead criado com sucesso!
-- 
-- 📌 ATENÇÃO: O Observer NÃO é disparado via SQL direto!
-- 
-- Para testar o atendimento automático:
-- 1. Acesse: http://127.0.0.1:8000/app/leads.html
-- 2. Faça login com: admin@exclusiva.com / password
-- 3. Clique no botão 🤖 ao lado do lead criado
-- 
-- Ou configure o webhook do Chaves na Mão:
-- POST http://127.0.0.1:8000/webhook/chavesnamao
-- ==================================================
