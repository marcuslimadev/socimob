<?php

require_once 'bootstrap/app.php';

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;

echo "🧪 Simulando recebimento de 'oi' via webhook Twilio...\n\n";

// Simular dados do Twilio (formato real)
$webhookData = [
    'AccountSid' => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'MessageSid' => 'SMxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'From' => '+5531987654321',  // Twilio envia sem 'whatsapp:' prefix
    'To' => '+5531999999999',    // Twilio envia sem 'whatsapp:' prefix
    'Body' => 'oi',
    'ProfileName' => 'João Silva'
];

try {
    $whatsappService = app(WhatsAppService::class);
    
    echo "📱 Dados do webhook:\n";
    echo json_encode($webhookData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "⏳ Processando mensagem...\n\n";
    
    // IMPORTANTE: Usar o WebhookController para normalizar os dados
    $webhookController = app(\App\Http\Controllers\WebhookController::class);
    
    // Usar reflexão para acessar o método privado normalizeWebhookData
    $reflection = new \ReflectionMethod($webhookController, 'normalizeWebhookData');
    $reflection->setAccessible(true);
    
    // Também precisa do detectWebhookSource (privado)
    $reflection2 = new \ReflectionMethod($webhookController, 'detectWebhookSource');
    $reflection2->setAccessible(true);
    $source = $reflection2->invoke($webhookController, $webhookData);
    
    $normalizedData = $reflection->invoke($webhookController, $webhookData, $source);
    
    echo "📦 Dados normalizados:\n";
    echo json_encode($normalizedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    $result = $whatsappService->processIncomingMessage($normalizedData);
    
    echo "✅ Resultado do processamento:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
} catch (\Throwable $e) {
    echo "❌ ERRO:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n═════════════════════════════════════════════════════════════════\n";
echo "📋 Verifique os logs em: storage/logs/lumen-*.log\n";
