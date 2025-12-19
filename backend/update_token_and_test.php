<?php

// Conectar diretamente ao MySQL para atualizar o token
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=exclusiva', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $novoToken = '$2y$10$Lcn1ct.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O';
    
    echo "=== ATUALIZANDO TOKEN DO TENANT ===\n\n";
    
    // Atualizar o token
    $stmt = $pdo->prepare("UPDATE tenants SET api_token_externa = ? WHERE id = 1");
    $stmt->execute([$novoToken]);
    
    echo "Token atualizado com sucesso!\n";
    
    // Verificar se foi atualizado
    $stmt = $pdo->prepare("SELECT id, name, api_token_externa FROM tenants WHERE id = 1");
    $stmt->execute();
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Token atual no banco: " . $tenant['api_token_externa'] . "\n";
    echo "Tamanho do token: " . strlen($tenant['api_token_externa']) . " caracteres\n\n";
    
    // Agora vamos testar o novo token
    echo "=== TESTANDO NOVO TOKEN ===\n\n";
    
    $url = "https://www.exclusivalarimoveis.com.br/api/v1/app/imovel/lista";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . "?pagina=1&limite=5");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'token: ' . $novoToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status HTTP: $httpCode\n";
    
    if ($httpCode === 200) {
        echo "🎉 SUCESSO! Token válido!\n\n";
        
        $data = json_decode($response, true);
        if (isset($data['resultSet']['data'])) {
            $count = count($data['resultSet']['data']);
            echo "Imóveis encontrados: $count\n";
            echo "Primeiro imóvel: " . ($data['resultSet']['data'][0]['titulo'] ?? 'N/A') . "\n";
        }
        
        echo "\nResposta completa (primeiros 500 chars):\n";
        echo substr($response, 0, 500) . "...\n";
        
    } else {
        echo "❌ Erro ainda persiste\n";
        echo "Resposta: $response\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}