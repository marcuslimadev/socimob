<?php

// Teste de conectividade básica com a API Exclusiva

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

echo "🌐 Teste de conectividade com Exclusiva...\n\n";

$baseUrl = 'https://www.exclusivalarimoveis.com.br';
$token = '$2y$10$Lcn1ct.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O';

$client = new Client([
    'verify' => false,
    'timeout' => 30,
    'http_errors' => false
]);

// Teste 1: Verificar se o site responde
echo "1️⃣ Testando conectividade básica...\n";
try {
    $response = $client->get($baseUrl);
    echo "Status do site principal: " . $response->getStatusCode() . "\n\n";
} catch (Exception $e) {
    echo "❌ Erro ao acessar site: " . $e->getMessage() . "\n\n";
}

// Teste 2: Tentar endpoint com método GET
echo "2️⃣ Testando endpoint com GET...\n";
$endpoint = '/api/v1/app/imovel/lista';
try {
    $response = $client->get($baseUrl . $endpoint, [
        'headers' => [
            'token' => $token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    echo "GET Status: " . $response->getStatusCode() . "\n";
    $body = $response->getBody()->getContents();
    echo "Response: " . substr($body, 0, 200) . "\n\n";
} catch (Exception $e) {
    echo "❌ Erro GET: " . $e->getMessage() . "\n\n";
}

// Teste 3: POST com headers mais completos
echo "3️⃣ Testando POST com headers completos...\n";
try {
    $response = $client->post($baseUrl . $endpoint, [
        'headers' => [
            'token' => $token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept' => '*/*',
            'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive'
        ],
        'json' => [
            'pagina' => 1,
            'limite' => 1
        ]
    ]);
    
    echo "POST Status: " . $response->getStatusCode() . "\n";
    $body = $response->getBody()->getContents();
    echo "Response: " . substr($body, 0, 500) . "\n\n";
    
    if ($response->getStatusCode() === 200) {
        $data = json_decode($body, true);
        if ($data) {
            echo "📊 Dados decodificados:\n";
            echo "Status: " . ($data['status'] ?? 'N/A') . "\n";
            echo "Message: " . ($data['message'] ?? 'N/A') . "\n";
            if (isset($data['resultSet'])) {
                echo "ResultSet presente!\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro POST: " . $e->getMessage() . "\n";
}

// Teste 4: Verificar se o token está correto tentando um endpoint de autenticação
echo "\n4️⃣ Verificando autenticação...\n";
$authEndpoints = ['/api/auth/verify', '/api/v1/auth', '/auth', '/api/user'];
foreach ($authEndpoints as $authEndpoint) {
    try {
        $response = $client->get($baseUrl . $authEndpoint, [
            'headers' => [
                'token' => $token,
                'Content-Type' => 'application/json'
            ]
        ]);
        echo "$authEndpoint: " . $response->getStatusCode() . "\n";
        if ($response->getStatusCode() !== 404) {
            echo "Response: " . substr($response->getBody()->getContents(), 0, 100) . "\n";
        }
    } catch (Exception $e) {
        echo "$authEndpoint: Erro - " . $e->getMessage() . "\n";
    }
}

echo "\n✓ Testes de conectividade finalizados\n";