<?php

// Gerar token de teste para a API

$email = 'contato@exclusivalarimoveis.com.br';
$password = 'password';

echo "🔑 Gerando token de login...\n\n";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/login');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $email,
    'password' => $password
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "Status: $httpCode\n";
echo "Response: $response\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['token'])) {
        echo "\n✅ Token obtido: " . $data['token'] . "\n";
        
        // Salvar em arquivo para usar depois
        file_put_contents(__DIR__ . '/token_temp.txt', $data['token']);
        echo "Token salvo em token_temp.txt\n";
    }
} else {
    echo "\n❌ Erro ao fazer login\n";
}

echo "\n✓ Finalizado\n";