<?php
/**
 * Enviar template "contatoinicial" para Marcus André
 * Execução simples sem prompts interativos
 */

require __DIR__ . '/vendor/autoload.php';

use App\Services\TwilioService;

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "===================================================================\n";
echo "  ENVIO TEMPLATE TWILIO: contatoinicial para Marcus André         \n";
echo "===================================================================\n\n";

$templateSid = 'HX4f61c2b07ceef4afc402a9c4753300df';
$templateName = 'contatoinicial';
$destinatario = '+5592992287144';

echo "Configurações:\n";
echo "- Template: $templateName\n";
echo "- SID: $templateSid\n";
echo "- Destinatário: $destinatario (Marcus André)\n";
echo "- Remetente: " . env('EXCLUSIVA_TWILIO_WHATSAPP_FROM') . "\n\n";

echo "Iniciando envio...\n\n";

try {
    $twilioService = new TwilioService();
    $resultado = $twilioService->sendTemplate($destinatario, $templateSid, []);

    echo "===================================================================\n";
    echo "                      RESULTADO DO ENVIO                           \n";
    echo "===================================================================\n\n";

    echo "Success: " . ($resultado['success'] ? 'SIM ✓' : 'NÃO ✗') . "\n";
    echo "HTTP Code: " . ($resultado['http_code'] ?? 'N/A') . "\n";
    echo "Message SID: " . ($resultado['message_sid'] ?? 'N/A') . "\n";
    echo "Status: " . ($resultado['status'] ?? 'N/A') . "\n";

    if (!empty($resultado['error'])) {
        echo "\nErro: " . $resultado['error'] . "\n";
    }

    if (!empty($resultado['response'])) {
        echo "\nResposta da API:\n";
        echo json_encode($resultado['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

    echo "\n";

    if ($resultado['success']) {
        echo "✓✓✓ MENSAGEM ENVIADA COM SUCESSO! ✓✓✓\n";
        echo "Aguarde alguns segundos para receber no WhatsApp +5592992287144\n";
    } else {
        echo "✗✗✗ FALHA NO ENVIO ✗✗✗\n";
        if (isset($resultado['response']['message'])) {
            echo "Mensagem de erro: " . $resultado['response']['message'] . "\n";
        }
        if (isset($resultado['response']['code'])) {
            echo "Código de erro: " . $resultado['response']['code'] . "\n";
        }
    }

} catch (Exception $e) {
    echo "\n===================================================================\n";
    echo "                        EXCEÇÃO                                     \n";
    echo "===================================================================\n\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n===================================================================\n";
echo "                         FIM                                       \n";
echo "===================================================================\n";
