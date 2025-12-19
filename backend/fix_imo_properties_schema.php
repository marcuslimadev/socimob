<?php

require_once 'vendor/autoload.php';

// Configurar app
$app = require_once 'bootstrap/app.php';

try {
    echo "🔧 Corrigindo schema da tabela imo_properties...\n\n";
    
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=exclusiva', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar se colunas existem
    $stmt = $pdo->query("DESCRIBE imo_properties");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Colunas atuais: " . implode(', ', $columns) . "\n\n";
    
    // Adicionar colunas ausentes
    $alterations = [];
    
    if (!in_array('tenant_id', $columns)) {
        $alterations[] = "ADD COLUMN tenant_id BIGINT UNSIGNED NULL DEFAULT 1";
        echo "➕ Adicionando coluna tenant_id\n";
    }
    
    if (!in_array('external_id', $columns)) {
        $alterations[] = "ADD COLUMN external_id VARCHAR(255) NULL";
        echo "➕ Adicionando coluna external_id\n";
    }
    
    if (!in_array('finalidade_imovel', $columns)) {
        $alterations[] = "ADD COLUMN finalidade_imovel VARCHAR(100) NULL";
        echo "➕ Adicionando coluna finalidade_imovel\n";
    }
    
    if (!in_array('tipo_imovel', $columns)) {
        $alterations[] = "ADD COLUMN tipo_imovel VARCHAR(100) NULL";
        echo "➕ Adicionando coluna tipo_imovel\n";
    }
    
    if (!in_array('preco', $columns)) {
        $alterations[] = "ADD COLUMN preco DECIMAL(15,2) NULL";
        echo "➕ Adicionando coluna preco\n";
    }
    
    if (!in_array('endereco', $columns)) {
        $alterations[] = "ADD COLUMN endereco TEXT NULL";
        echo "➕ Adicionando coluna endereco\n";
    }
    
    if (!in_array('cidade', $columns)) {
        $alterations[] = "ADD COLUMN cidade VARCHAR(255) NULL";
        echo "➕ Adicionando coluna cidade\n";
    }
    
    if (!in_array('estado', $columns)) {
        $alterations[] = "ADD COLUMN estado VARCHAR(2) NULL";
        echo "➕ Adicionando coluna estado\n";
    }
    
    if (!in_array('area_total', $columns)) {
        $alterations[] = "ADD COLUMN area_total DECIMAL(10,2) NULL";
        echo "➕ Adicionando coluna area_total\n";
    }
    
    if (!in_array('quartos', $columns)) {
        $alterations[] = "ADD COLUMN quartos INT NULL";
        echo "➕ Adicionando coluna quartos\n";
    }
    
    if (!in_array('banheiros', $columns)) {
        $alterations[] = "ADD COLUMN banheiros INT NULL";
        echo "➕ Adicionando coluna banheiros\n";
    }
    
    if (!in_array('vagas', $columns)) {
        $alterations[] = "ADD COLUMN vagas INT NULL";
        echo "➕ Adicionando coluna vagas\n";
    }
    
    if (!in_array('fotos', $columns)) {
        $alterations[] = "ADD COLUMN fotos JSON NULL";
        echo "➕ Adicionando coluna fotos\n";
    }
    
    if (!in_array('url_ficha', $columns)) {
        $alterations[] = "ADD COLUMN url_ficha VARCHAR(500) NULL";
        echo "➕ Adicionando coluna url_ficha\n";
    }
    
    // Executar alterações
    if (!empty($alterations)) {
        $sql = "ALTER TABLE imo_properties " . implode(", ", $alterations);
        echo "\n🔧 Executando: " . substr($sql, 0, 100) . "...\n";
        $pdo->exec($sql);
        echo "✅ Schema atualizado com sucesso!\n";
    } else {
        echo "✅ Tabela já está com o schema correto!\n";
    }
    
    // Adicionar índices importantes
    echo "\n🔧 Adicionando índices...\n";
    
    try {
        $pdo->exec("CREATE INDEX idx_tenant_external ON imo_properties(tenant_id, external_id)");
        echo "➕ Índice tenant_external criado\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') === false) {
            echo "⚠️  Erro ao criar índice: " . $e->getMessage() . "\n";
        } else {
            echo "ℹ️  Índice tenant_external já existe\n";
        }
    }
    
    // Verificar resultado final
    echo "\n📋 Verificando resultado...\n";
    $stmt = $pdo->query("DESCRIBE imo_properties");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Tabela imo_properties atualizada:\n";
    foreach ($finalColumns as $col) {
        echo "   - {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n🎉 Schema corrigido com sucesso!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}