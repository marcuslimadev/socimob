<?php

/**
 * Script direto para testar atendimento IA via API
 */

// Endpoint da API
$url = 'http://127.0.0.1:8000/api/admin/leads/56/iniciar-atendimento';

// Fazer login primeiro
$loginUrl = 'http://127.0.0.1:8000/api/auth/login';
$loginData = [
    'email' => 'admin@exclusiva.com',
    'password' => 'password'
];

echo "═══════════════════════════════════════════════════════════════\n";
echo " TESTE ATENDIMENTO IA - LEAD 56 VIA API\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Login
echo "📋 ETAPA 1: Fazendo login...\n";

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die("❌ Erro ao fazer login! HTTP $httpCode\nResposta: $response\n");
}

$loginResponse = json_decode($response, true);
$token = $loginResponse['token'] ?? null;

if (!$token) {
    die("❌ Token não recebido!\nResposta: $response\n");
}

echo "✅ Login OK! Token: " . substr($token, 0, 50) . "...\n\n";

// 2. Iniciar atendimento
echo "📋 ETAPA 2: Iniciando atendimento IA...\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['force' => true]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n\n";

$result = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Resposta não é JSON válido:\n";
    echo "$response\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo " RESULTADO\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if ($result['success'] ?? false) {
    echo "✅ SUCESSO!\n";
} else {
    echo "❌ ERRO: " . ($result['error'] ?? 'Desconhecido') . "\n";
}
