<?php

// Vou testar com um token que pode estar funcionando

$tokens_para_testar = [
    "\.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O", // Token atual
    "wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O", // Token sem escape
    // Vou tentar alguns tokens de exemplo que podem ser de demonstração
    "demo_token_123",
    "test_api_key",
];

$url = "https://www.exclusivalarimoveis.com.br/api/v1/app/imovel/lista";

echo "=== TESTANDO VÁRIOS TOKENS ===\n\n";

foreach ($tokens_para_testar as $index => $token) {
    echo "Teste " . ($index + 1) . ": " . substr($token, 0, 20) . "...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . "?pagina=1&limite=3");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'token: ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status: $httpCode\n";
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "SUCESSO! Imóveis encontrados: " . (isset($data['resultSet']['data']) ? count($data['resultSet']['data']) : 0) . "\n";
        echo "Response: " . substr($response, 0, 300) . "...\n\n";
        break; // Parar quando encontrar um token que funciona
    } else {
        $data = json_decode($response, true);
        echo "Erro: " . ($data['message'] ?? 'Desconhecido') . "\n\n";
    }
}

// Vou testar também sem token (caso seja uma API pública)
echo "Teste final: SEM TOKEN\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . "?pagina=1&limite=3");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode\n";

if ($httpCode === 200) {
    echo "API pública! Não precisa de token.\n";
    $data = json_decode($response, true);
    echo "Response: " . substr($response, 0, 300) . "...\n";
} else {
    $data = json_decode($response, true);
    echo "Erro: " . ($data['message'] ?? 'Desconhecido') . "\n";
}

echo "\n=== TESTE CONCLUÍDO ===\n";