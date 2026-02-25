<?php
require_once 'bootstrap/app.php';

// SUBSTITUA POR SEU TOKEN REAL
$token = 'SEU_TOKEN_AQUI';

if ($token === 'SEU_TOKEN_AQUI') {
    echo "❌ Configure SEU_TOKEN_AQUI com o token real\n";
    exit(1);
}

// Update via env or via database
echo "Atualizando token de Imobi Brasil...\n";

$tenant = \App\Models\Tenant::find(1);
$tenant->imobi_brasil_api_key = $token;
$tenant->save();

echo "✅ Token configurado com sucesso!\n";
echo "Token: " . substr($token, 0, 20) . "...\n";

// Test connection
echo "\nTestando conexão...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://exclusivalarimoveis.com.br/api/v1/imovel/lista',
    CURLOPT_HTTPHEADER => [
        'token: ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ Token válido! API respondeu com sucesso\n";
} elseif ($httpCode === 401 || $httpCode === 403) {
    echo "❌ Token inválido (HTTP $httpCode)\n";
    echo "Erro: " . substr($response, 0, 100) . "\n";
} else {
    echo "⚠️ Resposta inesperada (HTTP $httpCode)\n";
}
