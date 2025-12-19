<?php

$token = "\.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O";
$url = "https://www.exclusivalarimoveis.com.br/api/v1/app/imovel/lista";

echo "=== TESTANDO DIFERENTES FORMATOS DE TOKEN ===\n\n";

// Teste 1: Header 'token'
echo "1. Testando com header 'token':\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . "?pagina=1&limite=5");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'token: ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response1 = curl_exec($ch);
$httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode1\n";
echo "Response: " . substr($response1, 0, 200) . "...\n\n";

// Teste 2: Header 'Authorization'
echo "2. Testando com header 'Authorization':\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . "?pagina=1&limite=5");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode2\n";
echo "Response: " . substr($response2, 0, 200) . "...\n\n";

// Teste 3: Header 'X-API-Token'
echo "3. Testando com header 'X-API-Token':\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . "?pagina=1&limite=5");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Token: ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response3 = curl_exec($ch);
$httpCode3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode3\n";
echo "Response: " . substr($response3, 0, 200) . "...\n\n";

// Teste 4: Parâmetro na URL
echo "4. Testando com token na URL:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . "?pagina=1&limite=5&token=" . urlencode($token));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response4 = curl_exec($ch);
$httpCode4 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode4\n";
echo "Response: " . substr($response4, 0, 200) . "...\n\n";

// Teste 5: POST com token no body
echo "5. Testando POST com token no body:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'token' => $token,
    'pagina' => 1,
    'limite' => 5
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response5 = curl_exec($ch);
$httpCode5 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode5\n";
echo "Response: " . substr($response5, 0, 200) . "...\n\n";

echo "=== RESUMO ===\n";
echo "Teste 1 (header 'token'): $httpCode1\n";
echo "Teste 2 (Authorization Bearer): $httpCode2\n";
echo "Teste 3 (X-API-Token): $httpCode3\n";
echo "Teste 4 (token na URL): $httpCode4\n";
echo "Teste 5 (POST token no body): $httpCode5\n";