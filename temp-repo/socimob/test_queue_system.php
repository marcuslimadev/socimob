<?php
/**
 * Script de teste para o sistema de fila
 */

// Conectar ao banco diretamente
$host = 'srv1005.hstgr.io';
$dbname = 'u815655858_saas';
$username = 'u815655858_saas';
$password = 'Ekbt5WOqy#hJg#';

try {
    $db = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "🧪 TESTE DO SISTEMA DE FILA\n";
    echo str_repeat("=", 50) . "\n\n";

// 1. Verificar conversas existentes
echo "1️⃣ Conversas existentes:\n";
$conversas = $db->query("
    SELECT c.id, c.lead_id, c.corretor_id, c.status, l.nome as lead_nome
    FROM conversas c
    LEFT JOIN leads l ON c.lead_id = l.id
    WHERE c.tenant_id = 1
    ORDER BY c.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($conversas as $conv) {
    $status = $conv['corretor_id'] ? "Atribuída ao corretor {$conv['corretor_id']}" : "NA FILA";
    echo "  - Conversa #{$conv['id']} ({$conv['lead_nome']}): {$status}\n";
}

echo "\n";

// 2. Estatísticas da fila
echo "2️⃣ Estatísticas:\n";
$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN corretor_id IS NULL THEN 1 ELSE 0 END) as em_fila,
        SUM(CASE WHEN corretor_id IS NOT NULL THEN 1 ELSE 0 END) as atribuidas
    FROM conversas
    WHERE tenant_id = 1 AND status = 'ativa'
")->fetch(PDO::FETCH_ASSOC);

echo "  Total Ativas: {$stats['total']}\n";
echo "  Na Fila: {$stats['em_fila']}\n";
echo "  Atribuídas: {$stats['atribuidas']}\n";

echo "\n";

// 3. Criar uma conversa de teste na fila
echo "3️⃣ Criando conversa de teste na fila...\n";

// Verificar se já existe lead de teste
$testLead = $db->query("
    SELECT id FROM leads WHERE telefone = '+5531999887766' AND tenant_id = 1
")->fetch(PDO::FETCH_ASSOC);

if (!$testLead) {
    // Criar lead de teste
    $db->exec("
        INSERT INTO leads (tenant_id, nome, telefone, origem, status, created_at, updated_at)
        VALUES (1, 'Lead Teste Fila', '+5531999887766', 'teste', 'novo', NOW(), NOW())
    ");
    $leadId = $db->lastInsertId();
    echo "  ✅ Lead criado: ID {$leadId}\n";
} else {
    $leadId = $testLead['id'];
    echo "  ℹ️  Usando lead existente: ID {$leadId}\n";
}

// Verificar se já existe conversa para este lead
$existingConv = $db->query("
    SELECT id FROM conversas WHERE lead_id = {$leadId} AND tenant_id = 1
")->fetch(PDO::FETCH_ASSOC);

if (!$existingConv) {
    // Criar conversa sem corretor (na fila)
    $db->exec("
        INSERT INTO conversas (tenant_id, lead_id, corretor_id, status, created_at, updated_at)
        VALUES (1, {$leadId}, NULL, 'ativa', NOW(), NOW())
    ");
    $conversaId = $db->lastInsertId();
    echo "  ✅ Conversa criada na fila: ID {$conversaId}\n";
} else {
    $conversaId = $existingConv['id'];
    // Garantir que está na fila
    $db->exec("UPDATE conversas SET corretor_id = NULL, status = 'ativa' WHERE id = {$conversaId}");
    echo "  ✅ Conversa resetada para fila: ID {$conversaId}\n";
}

echo "\n";

// 4. Simular pegar próximo da fila
echo "4️⃣ Simulando corretor pegando da fila...\n";

// Buscar um corretor
$corretor = $db->query("
    SELECT id, name FROM users WHERE tenant_id = 1 AND role = 'corretor' LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if ($corretor) {
    echo "  Corretor: {$corretor['name']} (ID {$corretor['id']})\n";
    
    // Pegar próxima da fila (FIFO)
    $proxima = $db->query("
        SELECT id, lead_id
        FROM conversas
        WHERE tenant_id = 1 AND corretor_id IS NULL AND status = 'ativa'
        ORDER BY created_at ASC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($proxima) {
        // Atribuir ao corretor
        $db->exec("
            UPDATE conversas 
            SET corretor_id = {$corretor['id']}, updated_at = NOW()
            WHERE id = {$proxima['id']}
        ");
        
        echo "  ✅ Conversa #{$proxima['id']} atribuída ao corretor!\n";
        
        // Criar log
        $db->exec("
            INSERT INTO system_logs (tenant_id, level, category, message, context, created_at)
            VALUES (
                1,
                'info',
                'queue',
                'Conversa atribuída via fila',
                '{\"conversa_id\": {$proxima['id']}, \"corretor_id\": {$corretor['id']}, \"corretor_name\": \"{$corretor['name']}\"}',
                NOW()
            )
        ");
        echo "  ✅ Log registrado\n";
    } else {
        echo "  ⚠️  Nenhuma conversa disponível na fila\n";
    }
} else {
    echo "  ⚠️  Nenhum corretor encontrado para teste\n";
}

echo "\n";

// 5. Estatísticas finais
echo "5️⃣ Estatísticas finais:\n";
$finalStats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN corretor_id IS NULL THEN 1 ELSE 0 END) as em_fila,
        SUM(CASE WHEN corretor_id IS NOT NULL THEN 1 ELSE 0 END) as atribuidas
    FROM conversas
    WHERE tenant_id = 1 AND status = 'ativa'
")->fetch(PDO::FETCH_ASSOC);

echo "  Total Ativas: {$finalStats['total']}\n";
echo "  Na Fila: {$finalStats['em_fila']}\n";
echo "  Atribuídas: {$finalStats['atribuidas']}\n";

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Teste completo!\n";

} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
