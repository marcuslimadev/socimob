<?php

// Dados do lead
$leadData = [
    'nome' => 'Marcus',
    'telefone' => '+5592992287144',
    'whatsapp' => '+5592992287144',
    'email' => 'marcus@teste.com',
    'status' => 'novo',
    'observacoes' => 'Lead criado para teste de atendimento IA',
    'quartos' => 2
];

echo "═══════════════════════════════════════════════════════════════\n";
echo " CRIANDO LEAD MARCUS VIA API\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Login
$loginUrl = 'http://127.0.0.1:8000/api/auth/login';
$loginData = [
    'email' => 'admin@exclusiva.com',
    'password' => 'password'
];

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$loginResult = json_decode($response, true);

if ($httpCode !== 200 || !isset($loginResult['token'])) {
    echo "❌ Erro no login\n";
    exit(1);
}

$token = $loginResult['token'];
echo "✅ Login OK!\n\n";

// 2. Criar lead
$createLeadUrl = 'http://127.0.0.1:8000/api/admin/leads';

$ch = curl_init($createLeadUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($leadData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$createResult = json_decode($response, true);

echo "📋 Criar Lead - HTTP Code: {$httpCode}\n";
echo json_encode($createResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if ($httpCode !== 200 && $httpCode !== 201) {
    echo "❌ Erro ao criar lead\n";
    exit(1);
}

$leadId = $createResult['data']['id'] ?? $createResult['id'] ?? null;

if (!$leadId) {
    echo "❌ Lead ID não retornado\n";
    exit(1);
}

echo "✅ Lead criado! ID: {$leadId}\n\n";

// 3. Iniciar atendimento IA
echo "═══════════════════════════════════════════════════════════════\n";
echo " INICIANDO ATENDIMENTO IA\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$iniciarUrl = "http://127.0.0.1:8000/api/admin/leads/{$leadId}/iniciar-atendimento";

$ch = curl_init($iniciarUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$iniciarResult = json_decode($response, true);

echo "HTTP Code: {$httpCode}\n\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo " RESULTADO\n";
echo "═══════════════════════════════════════════════════════════════\n\n";
echo json_encode($iniciarResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if ($httpCode === 200 && isset($iniciarResult['success']) && $iniciarResult['success']) {
    echo "✅ SUCESSO! Atendimento IA iniciado para Marcus!\n";
    echo "\n📱 Mensagem enviada para: +5592992287144\n";
    if (isset($iniciarResult['data']['mensagem'])) {
        echo "\n💬 Conteúdo:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo wordwrap($iniciarResult['data']['mensagem'], 60, "\n") . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    }
} else {
    echo "❌ ERRO ao iniciar atendimento\n";
}
