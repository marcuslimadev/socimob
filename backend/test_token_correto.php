<?php

echo "🔑 Token configurado no .env:\n";
echo "Token: " . getenv('EXCLUSIVA_API_TOKEN') . "\n\n";

// Testar chamada à API
$token = '$2y$10$Lcn1ct.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O';
$url = 'https://www.exclusivalarimoveis.com.br/api/v1/app/imovel/lista?pagina=1&limite=5';

echo "🌐 Testando API com token correto...\n";
echo "URL: $url\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'token: ' . $token,
        'User-Agent: ExclusivaLar-CRM/1.0'
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "📡 Resposta:\n";
echo "HTTP Status: $httpCode\n";
echo "Body: " . substr($response, 0, 500) . "\n\n";

if ($httpCode === 200) {
    echo "✅ SUCESSO! Token válido!\n";
    $data = json_decode($response, true);
    if (isset($data['resultSet']['data'])) {
        echo "Total de imóveis encontrados: " . count($data['resultSet']['data']) . "\n";
    }
} else {
    echo "❌ ERRO: Status $httpCode\n";
}
