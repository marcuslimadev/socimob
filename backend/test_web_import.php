<?php

// Simular uma requisição para o endpoint de importação do sistema
$url = 'http://127.0.0.1:8000/api/admin/imoveis/importar';

// Token do usuário admin (vou pegar do banco)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=exclusiva', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar token do usuário admin
    $stmt = $pdo->prepare("SELECT auth_token FROM users WHERE email = 'contato@exclusivalarimoveis.com.br' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ Usuário admin não encontrado\n";
        exit;
    }
    
    $authToken = $user['auth_token'];
    echo "🔑 Token de autenticação encontrado\n\n";
    
} catch (Exception $e) {
    echo "❌ Erro ao buscar token: " . $e->getMessage() . "\n";
    exit;
}

// Fazer requisição de importação
echo "=== TESTANDO IMPORTAÇÃO VIA WEB ===\n\n";

$postData = json_encode([
    'fonte' => 'exclusiva'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $authToken
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 segundos para importação

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status HTTP: $httpCode\n";

if ($httpCode === 200) {
    echo "✅ Importação executada!\n\n";
    
    $data = json_decode($response, true);
    
    echo "Resposta da importação:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if (isset($data['success']) && $data['success']) {
        echo "🎉 IMPORTAÇÃO BEM-SUCEDIDA!\n";
        if (isset($data['importados'])) {
            echo "Imóveis importados: " . $data['importados'] . "\n";
        }
        if (isset($data['duplicados'])) {
            echo "Imóveis duplicados: " . $data['duplicados'] . "\n";
        }
        if (isset($data['erros'])) {
            echo "Erros: " . $data['erros'] . "\n";
        }
    }
    
} else {
    echo "❌ Erro na importação\n";
    echo "Resposta: $response\n";
}

// Verificar quantos imóveis temos no banco agora
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM properties WHERE tenant_id = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n=== RESULTADO NO BANCO ===\n";
    echo "Total de imóveis no banco: " . $result['total'] . "\n";
    
    if ($result['total'] > 0) {
        // Mostrar alguns exemplos
        $stmt = $pdo->prepare("SELECT title, price, property_type, created_at FROM properties WHERE tenant_id = 1 ORDER BY id DESC LIMIT 3");
        $stmt->execute();
        $imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nÚltimos imóveis importados:\n";
        foreach ($imoveis as $imovel) {
            echo "- {$imovel['title']} | {$imovel['property_type']} | R$ {$imovel['price']} | {$imovel['created_at']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erro ao verificar banco: " . $e->getMessage() . "\n";
}