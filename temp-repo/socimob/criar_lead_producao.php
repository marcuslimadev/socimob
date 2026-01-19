<?php
/**
 * Criar Lead de Teste no Banco de Produção
 */

echo "\n🧪 CRIAR LEAD DE TESTE NO BANCO DE PRODUÇÃO\n";
echo "==============================================\n\n";

try {
    echo "⏳ Tentando conectar ao banco de produção...\n";
    echo "   Host: srv1005.hstgr.io\n";
    echo "   Porta: 3306\n";
    echo "   Database: u815655858_saas\n\n";
    
    // Conectar ao banco de produção com timeout aumentado
    $pdo = new PDO(
        'mysql:host=srv1005.hstgr.io;port=3306;dbname=u815655858_saas;charset=utf8mb4',
        'u815655858_saas',
        'MundoMelhor@10',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );

    echo "✅ Conectado ao banco de produção!\n\n";

    // Dados do lead de teste
    $email = 'joao.teste.ia.' . time() . '@email.com';
    
    $sql = "INSERT INTO leads (
        tenant_id, nome, email, telefone, whatsapp, status, observacoes,
        quartos, preferencia_tipo_imovel, preferencia_bairro, 
        budget_min, budget_max, created_at, updated_at
    ) VALUES (
        :tenant_id, :nome, :email, :telefone, :whatsapp, :status, :observacoes,
        :quartos, :preferencia_tipo_imovel, :preferencia_bairro,
        :budget_min, :budget_max, NOW(), NOW()
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tenant_id' => 1,
        ':nome' => 'João Silva Teste IA',
        ':email' => $email,
        ':telefone' => '+5531987654321',
        ':whatsapp' => '+5531987654321',
        ':status' => 'novo',
        ':observacoes' => "Lead recebido via integração Chaves na Mão\nTeste de atendimento automático IA\nInteresse: Apartamento 2 quartos para compra",
        ':quartos' => 2,
        ':preferencia_tipo_imovel' => 'Apartamento',
        ':preferencia_bairro' => 'Centro, Savassi',
        ':budget_min' => 250000,
        ':budget_max' => 400000,
    ]);

    $leadId = $pdo->lastInsertId();

    echo "✅ LEAD CRIADO COM SUCESSO!\n";
    echo "==============================================\n";
    echo "ID: {$leadId}\n";
    echo "Nome: João Silva Teste IA\n";
    echo "Email: {$email}\n";
    echo "Telefone: +5531987654321\n";
    echo "Status: novo\n";
    echo "Tenant ID: 1\n";
    echo "==============================================\n\n";

    echo "🎯 PRÓXIMOS PASSOS:\n";
    echo "-------------------------------------------\n";
    echo "1. Acesse: http://127.0.0.1:8000/app/login.html\n";
    echo "2. Login: admin@exclusiva.com / password\n";
    echo "3. Vá em Leads e clique no botão 🤖 ao lado do lead\n";
    echo "4. Ou ative o atendimento automático em Configurações\n\n";

} catch (\PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
