<?php
/**
 * Script para testar a autenticação do webhook Chaves na Mão
 */

$email = 'contato@exclusivalarimoveis.com.br';
$token = 'd825c542e26df27c9fe696c391ee590';

// Gerar Basic Auth
$credentials = $email . ':' . $token;
$basicAuth = base64_encode($credentials);

echo "=== TESTE DE AUTENTICAÇÃO WEBHOOK ===\n\n";
echo "Email: $email\n";
echo "Token: $token\n\n";
echo "Credenciais: $credentials\n";
echo "Basic Auth: $basicAuth\n\n";

// Testar webhook
$url = 'https://exclusivalarimoveis.com/webhook/chaves-na-mao';

$payload = [
    'id' => 'TESTE_AUTH_' . time(),
    'name' => 'Teste Autenticação',
    'phone' => '11999999999',
    'email' => 'teste@auth.com',
    'segment' => 'REAL_ESTATE',
    'ad' => [
        'type' => 'Apartamento',
        'rooms' => 3
    ]
];

echo "Testando URL: $url\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . $basicAuth
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Status HTTP: $httpCode\n";
if ($error) {
    echo "Erro cURL: $error\n";
}
echo "Resposta:\n";
echo $response . "\n";

if ($httpCode == 200) {
    echo "\n✅ SUCESSO! Autenticação funcionando!\n";
} else {
    echo "\n❌ FALHA! Verificar logs em: storage/logs/lumen-" . date('Y-m-d') . ".log\n";
}
