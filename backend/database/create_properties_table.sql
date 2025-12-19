-- Criar tabela de imóveis (properties)

CREATE TABLE IF NOT EXISTS properties (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    external_id VARCHAR(255) NULL COMMENT 'ID da API externa',
    titulo VARCHAR(255) NOT NULL,
    tipo ENUM('casa', 'apartamento', 'terreno', 'comercial') DEFAULT 'casa',
    finalidade ENUM('venda', 'aluguel') DEFAULT 'venda',
    preco DECIMAL(12, 2) DEFAULT 0,
    area DECIMAL(10, 2) NULL,
    quartos INT DEFAULT 0,
    banheiros INT DEFAULT 0,
    vagas INT DEFAULT 0,
    endereco VARCHAR(255) NOT NULL,
    bairro VARCHAR(255) NULL,
    cidade VARCHAR(255) NULL,
    estado VARCHAR(2) NULL,
    cep VARCHAR(10) NULL,
    descricao TEXT NULL,
    fotos JSON NULL,
    status ENUM('disponivel', 'reservado', 'vendido', 'alugado') DEFAULT 'disponivel',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_status (tenant_id, status, is_active),
    UNIQUE KEY unique_tenant_external (tenant_id, external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar campos de API externa na tabela tenants
ALTER TABLE tenants 
ADD COLUMN IF NOT EXISTS api_url_externa VARCHAR(500) NULL AFTER api_key_neca,
ADD COLUMN IF NOT EXISTS api_token_externa VARCHAR(500) NULL AFTER api_url_externa;

SELECT 'Properties table and API fields created successfully!' as result;
