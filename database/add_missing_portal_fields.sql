-- Adicionar apenas os campos que faltam

ALTER TABLE tenants 
ADD COLUMN slogan VARCHAR(500) NULL AFTER description;

ALTER TABLE tenants 
ADD COLUMN favicon_url VARCHAR(500) NULL AFTER logo_url;

-- Atualizar tenant existente com valores padrão
UPDATE tenants 
SET slogan = 'Encontre o Imóvel dos Seus Sonhos'
WHERE slogan IS NULL;

SELECT 'Portal fields added successfully!' as result;
