<?php

// Conectar ao banco para buscar usuário
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=exclusiva', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar usuário admin
    $stmt = $pdo->prepare("SELECT id, name, email, tenant_id FROM users WHERE email = 'contato@exclusivalarimoveis.com.br' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ Usuário admin não encontrado\n";
        exit;
    }
    
    echo "👤 Usuário encontrado:\n";
    echo "ID: {$user['id']}\n";
    echo "Nome: {$user['name']}\n";
    echo "Email: {$user['email']}\n";
    echo "Tenant ID: {$user['tenant_id']}\n\n";
    
    // Gerar token usando a mesma lógica do sistema
    $secret = 'your-secret-key'; // Pode usar qualquer string
    $timestamp = time();
    $tokenData = $user['id'] . '|' . $timestamp . '|' . $secret;
    $token = base64_encode($tokenData);
    
    echo "🔑 Token gerado: $token\n\n";
    
    // Testar o token
    echo "=== TESTANDO TOKEN GERADO ===\n\n";
    
    $url = 'http://127.0.0.1:8000/api/admin/settings';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status HTTP: $httpCode\n";
    
    if ($httpCode === 200) {
        echo "✅ Token válido! Autenticação funcionando.\n\n";
        
        // Agora testar a importação
        echo "=== TESTANDO IMPORTAÇÃO ===\n\n";
        
        $importUrl = 'http://127.0.0.1:8000/api/admin/imoveis/importar';
        
        $postData = json_encode([
            'fonte' => 'exclusiva'
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $importUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutos para importação
        
        echo "Executando importação...\n";
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Status HTTP: $httpCode\n";
        
        if ($httpCode === 200) {
            echo "✅ Importação executada!\n";
            
            $data = json_decode($response, true);
            echo "Resposta:\n";
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            
            // Verificar resultados no banco
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM properties WHERE tenant_id = ?");
            $stmt->execute([$user['tenant_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "Total de imóveis no banco: " . $result['total'] . "\n";
            
        } else {
            echo "❌ Erro na importação\n";
            echo "Resposta: $response\n";
        }
        
    } else {
        echo "❌ Token inválido\n";
        echo "Resposta: $response\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}