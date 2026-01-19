-- Adicionar campos do portal público à tabela tenants

ALTER TABLE tenants 
ADD COLUMN slogan VARCHAR(500) NULL AFTER description,
ADD COLUMN favicon_url VARCHAR(500) NULL AFTER logo_url,
ADD COLUMN primary_color VARCHAR(7) DEFAULT '#1e293b' AFTER favicon_url,
ADD COLUMN secondary_color VARCHAR(7) DEFAULT '#3b82f6' AFTER primary_color;

-- Atualizar tenant existente com valores padrão
UPDATE tenants 
SET slogan = 'Encontre o Imóvel dos Seus Sonhos',
    primary_color = '#1e293b',
    secondary_color = '#3b82f6'
WHERE slogan IS NULL;

SELECT 'Portal fields added successfully!' as result;
