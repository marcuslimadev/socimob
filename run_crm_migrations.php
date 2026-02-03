<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "=== Executando Migrations Manuais ===\n\n";
    
    // Adicionar colunas na tabela pessoas
    echo "Adicionando colunas CRM na tabela pessoas...\n";
    
    $columns = [
        "ADD COLUMN papeis JSON NULL AFTER tipo",
        "ADD COLUMN status VARCHAR(255) DEFAULT 'ativo' AFTER ativo",
        "ADD COLUMN origem VARCHAR(255) NULL AFTER status",
        "ADD COLUMN classificacao VARCHAR(255) NULL AFTER origem",
        "ADD COLUMN interesses JSON NULL AFTER classificacao",
        "ADD COLUMN preferencias JSON NULL AFTER interesses",
        "ADD COLUMN renda_mensal DECIMAL(15,2) NULL AFTER preferencias",
        "ADD COLUMN profissao VARCHAR(255) NULL AFTER renda_mensal",
        "ADD COLUMN empresa VARCHAR(255) NULL AFTER profissao",
        "ADD COLUMN documentos JSON NULL AFTER empresa",
        "ADD COLUMN fotos JSON NULL AFTER documentos",
        "ADD COLUMN corretor_responsavel_id BIGINT UNSIGNED NULL AFTER fotos",
        "ADD COLUMN indicado_por_id BIGINT UNSIGNED NULL AFTER corretor_responsavel_id",
        "ADD COLUMN score INT DEFAULT 0 AFTER indicado_por_id",
        "ADD COLUMN ultimo_atendimento TIMESTAMP NULL AFTER score",
        "ADD COLUMN ultimo_contato TIMESTAMP NULL AFTER ultimo_atendimento",
        "ADD COLUMN total_atendimentos INT DEFAULT 0 AFTER ultimo_contato",
        "ADD COLUMN total_imoveis_visitados INT DEFAULT 0 AFTER total_atendimentos",
        "ADD COLUMN tags JSON NULL AFTER total_imoveis_visitados",
        "ADD COLUMN facebook VARCHAR(255) NULL AFTER tags",
        "ADD COLUMN instagram VARCHAR(255) NULL AFTER facebook",
        "ADD COLUMN linkedin VARCHAR(255) NULL AFTER instagram",
        "ADD COLUMN whatsapp VARCHAR(255) NULL AFTER linkedin"
    ];
    
    foreach ($columns as $column) {
        try {
            DB::statement("ALTER TABLE pessoas $column");
            echo "  ✓ Coluna adicionada\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "  - Coluna já existe\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Criar índices
    echo "\nCriando índices...\n";
    $indexes = [
        "CREATE INDEX idx_pessoas_status ON pessoas(status)",
        "CREATE INDEX idx_pessoas_classificacao ON pessoas(classificacao)",
        "CREATE INDEX idx_pessoas_corretor ON pessoas(corretor_responsavel_id)",
        "CREATE INDEX idx_pessoas_score ON pessoas(score)"
    ];
    
    foreach ($indexes as $index) {
        try {
            DB::statement($index);
            echo "  ✓ Índice criado\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "  - Índice já existe\n";
            } else {
                echo "  ! Erro ao criar índice: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Criar tabela pessoa_interacoes
    echo "\nCriando tabela pessoa_interacoes...\n";
    DB::statement("
        CREATE TABLE IF NOT EXISTS pessoa_interacoes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            pessoa_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            tipo VARCHAR(255) NOT NULL,
            assunto VARCHAR(255) NULL,
            descricao TEXT NULL,
            metadata JSON NULL,
            resultado VARCHAR(255) NULL,
            data_interacao TIMESTAMP NOT NULL,
            proxima_acao TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            INDEX idx_pessoa_interacoes_pessoa (pessoa_id, data_interacao),
            INDEX idx_pessoa_interacoes_tenant_tipo (tenant_id, tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  ✓ Tabela pessoa_interacoes criada\n";
    
    // Criar tabela pessoa_documentos
    echo "\nCriando tabela pessoa_documentos...\n";
    DB::statement("
        CREATE TABLE IF NOT EXISTS pessoa_documentos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            pessoa_id BIGINT UNSIGNED NOT NULL,
            tipo VARCHAR(255) NOT NULL,
            nome VARCHAR(255) NOT NULL,
            arquivo VARCHAR(255) NOT NULL,
            tamanho INT NULL,
            mime_type VARCHAR(255) NULL,
            observacoes TEXT NULL,
            uploaded_by BIGINT UNSIGNED NULL,
            data_validade TIMESTAMP NULL,
            verificado TINYINT(1) DEFAULT 0,
            verificado_em TIMESTAMP NULL,
            verificado_por BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL,
            INDEX idx_pessoa_documentos_pessoa_tipo (pessoa_id, tipo),
            INDEX idx_pessoa_documentos_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  ✓ Tabela pessoa_documentos criada\n";
    
    // Criar tabela pessoa_relacionamentos
    echo "\nCriando tabela pessoa_relacionamentos...\n";
    DB::statement("
        CREATE TABLE IF NOT EXISTS pessoa_relacionamentos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            pessoa_origem_id BIGINT UNSIGNED NOT NULL,
            pessoa_destino_id BIGINT UNSIGNED NOT NULL,
            tipo VARCHAR(255) NOT NULL,
            observacoes TEXT NULL,
            ativo TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            INDEX idx_pessoa_relacionamentos_origem (pessoa_origem_id, tipo),
            INDEX idx_pessoa_relacionamentos_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  ✓ Tabela pessoa_relacionamentos criada\n";
    
    echo "\n✅ Migrations executadas com sucesso!\n";
    
} catch (Exception $e) {
    echo "\n❌ Erro: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
