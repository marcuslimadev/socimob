<?php
/**
 * Teste local do webhook WhatsApp
 * Simula uma mensagem do Twilio
 */

require __DIR__ . '/bootstrap/app.php';

$app = require __DIR__ . '/bootstrap/app.php';

echo "=== TESTE WEBHOOK WHATSAPP LOCAL ===\n\n";

// Dados simulando uma mensagem do Twilio
$twilioPayload = [
    'MessageSid' => 'SM' . bin2hex(random_bytes(16)),
    'AccountSid' => 'AC123456789',
    'MessagingServiceSid' => 'MG123456789',
    'From' => 'whatsapp:+5511999999999',
    'To' => 'whatsapp:+551140405050',
    'Body' => 'Olá, gostaria de informações sobre imóveis',
    'NumMedia' => '0',
    'ProfileName' => 'João Teste',
    'WaId' => '5511999999999',
    'SmsStatus' => 'received',
    'ApiVersion' => '2010-04-01'
];

echo "📤 Enviando payload simulado do Twilio:\n";
echo json_encode($twilioPayload, JSON_PRETTY_PRINT) . "\n\n";

// Criar requisição simulada
$request = Illuminate\Http\Request::create(
    '/webhook/whatsapp',
    'POST',
    $twilioPayload,
    [],
    [],
    [
        'HTTP_HOST' => '127.0.0.1:8000',
        'HTTP_USER_AGENT' => 'TwilioProxy/1.1',
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
    ]
);

try {
    // Instanciar controller
    $whatsappService = $app->make(App\Services\WhatsAppService::class);
    $controller = new App\Http\Controllers\WebhookController($whatsappService);
    
    echo "🔄 Processando webhook...\n\n";
    
    // Executar método receive
    $response = $controller->receive($request);
    
    echo "✅ Resposta do webhook:\n";
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content-Type: " . $response->headers->get('Content-Type') . "\n";
    echo "Body: " . $response->getContent() . "\n\n";
    
    echo "📋 Verifique os logs em storage/logs/ para detalhes\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO:\n";
    echo $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
