<?php
/**
 * UPLOAD ESTE ARQUIVO PARA: https://lojadaesquina.store/criar_lead_teste_remoto.php
 * Depois acesse no navegador para criar o lead
 */

// Conectar ao banco
$pdo = new PDO(
    'mysql:host=localhost;dbname=u815655858_saas;charset=utf8mb4',
    'u815655858_saas',
    'MundoMelhor@10',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Criar lead de teste
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

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lead Criado</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
        .success { color: #22c55e; font-size: 24px; margin-bottom: 20px; }
        .info { background: #f0f9ff; padding: 15px; border-radius: 6px; margin: 10px 0; }
        .label { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="success">✅ LEAD CRIADO COM SUCESSO!</div>
        
        <div class="info">
            <div><span class="label">ID:</span> <?= $leadId ?></div>
            <div><span class="label">Nome:</span> João Silva Teste IA</div>
            <div><span class="label">Email:</span> <?= $email ?></div>
            <div><span class="label">Telefone:</span> +5531987654321</div>
            <div><span class="label">Status:</span> novo</div>
        </div>

        <h3>🎯 Próximos Passos:</h3>
        <ol>
            <li>Acesse: <a href="https://lojadaesquina.store/app/login.html">Sistema</a></li>
            <li>Login: admin@exclusiva.com / password</li>
            <li>Vá em Leads e clique no botão 🤖 ao lado do lead</li>
        </ol>

        <p><strong>⚠️ IMPORTANTE:</strong> Delete este arquivo após usar!</p>
    </div>
</body>
</html>
