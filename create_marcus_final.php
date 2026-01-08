<?php

$host = 'srv1005.hstgr.io';
$db = 'u815655858_saas';
$user = 'u815655858_saas';
$pass = 'MundoMelhor@10';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conectado ao banco de produção\n\n";
    
    // Criar lead
    $stmt = $pdo->prepare("
        INSERT INTO leads (tenant_id, nome, telefone, whatsapp, status, quartos, created_at, updated_at) 
        VALUES (1, 'Marcus', '+5592992287144', '+5592992287144', 'novo', 2, NOW(), NOW())
    ");
    
    $stmt->execute();
    $leadId = $pdo->lastInsertId();
    
    echo "✅ Lead criado! ID: $leadId\n";
    echo "   Nome: Marcus\n";
    echo "   Telefone: +5592992287144\n\n";
    
    // Agora iniciar atendimento via API
    echo "Iniciando atendimento IA via API...\n\n";
    
    // Login
    $ch = curl_init('http://127.0.0.1:8000/api/auth/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => 'admin@exclusiva.com',
        'password' => 'password'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $loginResult = json_decode($response, true);
    curl_close($ch);
    
    if (!isset($loginResult['token'])) {
        die("❌ Erro no login\n");
    }
    
    $token = $loginResult['token'];
    echo "✅ Token obtido\n\n";
    
    // Iniciar atendimento
    $ch = curl_init("http://127.0.0.1:8000/api/admin/leads/$leadId/iniciar-atendimento");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    echo "═══════════════════════════════════════════════════════════════\n";
    echo " RESULTADO DO ATENDIMENTO IA\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    echo "HTTP Code: $httpCode\n\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if ($httpCode === 200 && $result['success']) {
        echo "✅ SUCESSO! Atendimento iniciado para Marcus!\n\n";
        echo "📱 Mensagem enviada para: +5592992287144\n\n";
        if (isset($result['data']['mensagem'])) {
            echo "💬 Mensagem:\n";
            echo str_repeat("-", 60) . "\n";
            echo wordwrap($result['data']['mensagem'], 60, "\n") . "\n";
            echo str_repeat("-", 60) . "\n";
        }
    } else {
        echo "❌ Erro ao iniciar atendimento\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
