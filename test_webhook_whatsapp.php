<?php
/**
 * Script para testar o webhook WhatsApp via ngrok
 * 
 * Uso: php test_webhook_whatsapp.php
 */

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║         🟢 TESTE WEBHOOK WHATSAPP VIA NGROK                      ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// URL do ngrok
$ngrokUrl = 'https://99a3345711a3.ngrok-free.app';
$webhookUrl = $ngrokUrl . '/webhook/whatsapp';

echo "🌐 URL do webhook: {$webhookUrl}\n\n";

// Simular mensagem do Twilio
$twilioPayload = [
    'MessageSid' => 'SM' . bin2hex(random_bytes(16)),
    'AccountSid' => 'AC' . bin2hex(random_bytes(16)),
    'From' => 'whatsapp:+5521987654321',
    'To' => 'whatsapp:+5521999887766',
    'Body' => 'Olá! Estou interessado em um imóvel.',
    'ProfileName' => 'João da Silva',
    'FromCity' => 'Rio de Janeiro',
    'FromState' => 'RJ',
    'FromCountry' => 'BR',
    'NumMedia' => '0'
];

echo "═══════════════════════════════════════════════════════════════════\n";
echo "📱 TESTE 1: Mensagem simulada do Twilio\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "Payload:\n";
echo json_encode($twilioPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "Enviando requisição...\n";
$result1 = sendWebhook($webhookUrl, $twilioPayload);
echo "\n";

// Simular mensagem da Evolution API
$evolutionPayload = [
    'event' => 'messages.upsert',
    'instance' => 'exclusiva_instance',
    'data' => [
        'key' => [
            'remoteJid' => '5521987654321@s.whatsapp.net',
            'fromMe' => false,
            'id' => '3EB0' . strtoupper(bin2hex(random_bytes(8)))
        ],
        'pushName' => 'Maria Santos',
        'message' => [
            'conversation' => 'Gostaria de agendar uma visita ao apartamento.'
        ],
        'messageTimestamp' => time()
    ]
];

echo "═══════════════════════════════════════════════════════════════════\n";
echo "📱 TESTE 2: Mensagem simulada da Evolution API\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "Payload:\n";
echo json_encode($evolutionPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "Enviando requisição...\n";
$result2 = sendWebhook($webhookUrl, $evolutionPayload);
echo "\n";

// Mensagem com mídia (Twilio)
$twilioMediaPayload = [
    'MessageSid' => 'SM' . bin2hex(random_bytes(16)),
    'AccountSid' => 'AC' . bin2hex(random_bytes(16)),
    'From' => 'whatsapp:+5521987654321',
    'To' => 'whatsapp:+5521999887766',
    'Body' => 'Segue foto do imóvel',
    'ProfileName' => 'Carlos Oliveira',
    'NumMedia' => '1',
    'MediaUrl0' => 'https://api.twilio.com/2010-04-01/Accounts/ACxxxx/Messages/MMxxxx/Media/MExxxx',
    'MediaContentType0' => 'image/jpeg'
];

echo "═══════════════════════════════════════════════════════════════════\n";
echo "📱 TESTE 3: Mensagem com mídia (Twilio)\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "Payload:\n";
echo json_encode($twilioMediaPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "Enviando requisição...\n";
$result3 = sendWebhook($webhookUrl, $twilioMediaPayload);
echo "\n";

// Resumo dos testes
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║                    📊 RESUMO DOS TESTES                           ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Teste 1 (Twilio básico):  " . ($result1['success'] ? '✅ Sucesso' : '❌ Falhou') . " - HTTP {$result1['status']}\n";
echo "Teste 2 (Evolution API):  " . ($result2['success'] ? '✅ Sucesso' : '❌ Falhou') . " - HTTP {$result2['status']}\n";
echo "Teste 3 (Twilio mídia):   " . ($result3['success'] ? '✅ Sucesso' : '❌ Falhou') . " - HTTP {$result3['status']}\n";
echo "\n";

// Dicas
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║                      💡 PRÓXIMOS PASSOS                           ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "1. Verifique os logs da aplicação em:\n";
echo "   storage/logs/lumen-" . date('Y-m-d') . ".log\n";
echo "\n";
echo "2. Configure o webhook no Twilio Console:\n";
echo "   URL: {$webhookUrl}\n";
echo "   Method: POST\n";
echo "\n";
echo "3. Para Evolution API, configure:\n";
echo "   Webhook URL: {$webhookUrl}\n";
echo "   Events: messages.upsert\n";
echo "\n";
echo "4. Certifique-se de que o ngrok está rodando:\n";
echo "   ngrok http 8000\n";
echo "\n";

/**
 * Envia webhook via cURL
 */
function sendWebhook($url, $payload) {
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: TwilioProxy/1.1',
            'X-Twilio-Signature: test_signature'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ Erro cURL: {$error}\n";
        return ['success' => false, 'status' => 0, 'error' => $error];
    }
    
    $success = ($httpCode >= 200 && $httpCode < 300);
    
    if ($success) {
        echo "✅ Resposta HTTP {$httpCode}: {$response}\n";
    } else {
        echo "❌ Resposta HTTP {$httpCode}: {$response}\n";
    }
    
    return [
        'success' => $success,
        'status' => $httpCode,
        'response' => $response
    ];
}
