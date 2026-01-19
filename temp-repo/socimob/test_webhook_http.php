<?php
/**
 * Teste do webhook WhatsApp via HTTP real
 * Simula uma requisição do Twilio no servidor local
 */

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║         📱 TESTE WEBHOOK WHATSAPP - HTTP LOCAL                   ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$baseUrl = 'http://localhost';
$webhookUrl = $baseUrl . '/webhook/whatsapp';

// ==========================================
// TESTE 1: GET (Validação Twilio)
// ==========================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 TESTE 1: GET /webhook/whatsapp (Validação)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: TwilioProxy/1.1'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status HTTP: " . $httpCode . "\n";
echo "Resposta: " . $response . "\n\n";

if ($httpCode == 200) {
    echo "✅ GET funcionando!\n\n";
} else {
    echo "❌ Erro no GET\n\n";
}

// ==========================================
// TESTE 2: POST (Mensagem do WhatsApp)
// ==========================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 TESTE 2: POST /webhook/whatsapp (Mensagem)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Simular payload do Twilio
$payload = [
    'MessageSid' => 'SM' . bin2hex(random_bytes(16)),
    'AccountSid' => 'AC123456789',
    'MessagingServiceSid' => 'MG123456789',
    'From' => 'whatsapp:+5511999999999',
    'To' => 'whatsapp:+551140405050',
    'Body' => 'Olá! Gostaria de informações sobre imóveis de 3 quartos na região.',
    'NumMedia' => '0',
    'ProfileName' => 'João Teste Local',
    'WaId' => '5511999999999',
    'SmsStatus' => 'received',
    'ApiVersion' => '2010-04-01'
];

echo "📤 Payload enviado:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: TwilioProxy/1.1'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "Status HTTP: " . $httpCode . "\n";

if ($curlError) {
    echo "❌ Erro cURL: " . $curlError . "\n\n";
} else {
    echo "Resposta: " . ($response ?: '(vazio)') . "\n\n";
    
    if ($httpCode == 200) {
        echo "✅ POST funcionando!\n\n";
    } else {
        echo "❌ Erro no POST\n\n";
    }
}

// ==========================================
// VERIFICAR LOGS
// ==========================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 LOGS RECENTES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$logFile = __DIR__ . '/storage/logs/lumen-' . date('Y-m-d') . '.log';

if (file_exists($logFile)) {
    echo "📁 Arquivo de log: " . basename($logFile) . "\n\n";
    
    // Ler últimas 20 linhas
    $lines = file($logFile);
    $lastLines = array_slice($lines, -20);
    
    echo "Últimas entradas:\n";
    echo "────────────────────────────────────────────────\n";
    foreach ($lastLines as $line) {
        echo $line;
    }
} else {
    echo "⚠️ Arquivo de log não encontrado\n";
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║         ✅ TESTES CONCLUÍDOS                                      ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

echo "📝 Próximos passos:\n";
echo "   1. Verificar se mensagem foi criada no banco (tabela 'mensagems')\n";
echo "   2. Verificar se conversa foi criada/atualizada (tabela 'conversas')\n";
echo "   3. Verificar se lead foi criado/atualizado (tabela 'leads')\n";
echo "   4. Testar com ngrok em produção\n";
echo "\n";
