<?php
// Teste simples da API Exclusiva

$baseUrl = 'https://www.exclusivalarimoveis.com.br';
$token = 'wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O';
$url = $baseUrl . '/api/v1/app/imovel/lista?pagina=1&limite=3';

echo "Testando URL: $url\n";
echo "Token: $token\n\n";

// Usar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'token: ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";

if ($error) {
    echo "Erro cURL: $error\n";
}

if ($response) {
    echo "Response (primeiros 1000 chars):\n" . substr($response, 0, 1000) . "\n";
    
    $data = json_decode($response, true);
    if ($data) {
        echo "\nJSON decodificado com sucesso!\n";
        echo "Status: " . ($data['status'] ? 'true' : 'false') . "\n";
        
        if (isset($data['resultSet']['data'])) {
            echo "Imóveis encontrados: " . count($data['resultSet']['data']) . "\n";
        }
    } else {
        echo "\nErro ao decodificar JSON!\n";
    }
} else {
    echo "Nenhuma resposta recebida\n";
}