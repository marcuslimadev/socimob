<?php
/**
 * Script de teste para enviar template WhatsApp via Twilio
 * Template: contatoinicial
 * SID: HX4f61c2b07ceef4afc402a9c4753300df
 */

require __DIR__ . '/vendor/autoload.php';

use App\Services\TwilioService;

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "===================================================================\n";
echo "        TESTE DE ENVIO - TEMPLATE TWILIO WHATSAPP                 \n";
echo "===================================================================\n\n";

// Configurações do template
$templateSid = 'HX4f61c2b07ceef4afc402a9c4753300df';
$templateName = 'contatoinicial';

// Número de destino - Marcus André
// Se você passar um número como argumento: php test_twilio_template.php +5592992287144 --yes
$destinatario = $argv[1] ?? null;
$autoConfirm = in_array('--yes', $argv) || in_array('-y', $argv);

if (!$destinatario) {
    echo "ERRO: Informe o número de destino como argumento\n";
    echo "Uso: php test_twilio_template.php +5592992287144 [--yes|-y]\n";
    echo "\nOu se preferir, digite o número agora: ";
    $destinatario = trim(fgets(STDIN));

    if (empty($destinatario)) {
        echo "Número não informado. Abortando.\n";
        exit(1);
    }
}

echo "Configurações:\n";
echo "- Template: $templateName\n";
echo "- SID: $templateSid\n";
echo "- Destinatário: $destinatario\n";
echo "- Remetente: " . env('EXCLUSIVA_TWILIO_WHATSAPP_FROM') . "\n\n";

if (!$autoConfirm) {
    echo "Deseja enviar a mensagem? (s/n): ";
    $confirmacao = trim(fgets(STDIN));

    if (strtolower($confirmacao) !== 's') {
        echo "Envio cancelado pelo usuário.\n";
        exit(0);
    }
} else {
    echo "Modo automático ativado (--yes). Prosseguindo...\n\n";
}

echo "\n--- Iniciando envio ---\n\n";

try {
    $twilioService = new TwilioService();

    // Variáveis do template (se houver)
    // O template "contatoinicial" pode ter variáveis como {{1}}, {{2}}, etc.
    // Ajuste conforme necessário
    $variables = [];

    $resultado = $twilioService->sendTemplate($destinatario, $templateSid, $variables);

    echo "Resultado do envio:\n";
    echo "- Success: " . ($resultado['success'] ? 'SIM' : 'NÃO') . "\n";
    echo "- HTTP Code: " . ($resultado['http_code'] ?? 'N/A') . "\n";
    echo "- Message SID: " . ($resultado['message_sid'] ?? 'N/A') . "\n";
    echo "- Status: " . ($resultado['status'] ?? 'N/A') . "\n";

    if (!empty($resultado['error'])) {
        echo "- Erro: " . $resultado['error'] . "\n";
    }

    if (!empty($resultado['response'])) {
        echo "\nResposta completa da API:\n";
        echo json_encode($resultado['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

    echo "\n";

    if ($resultado['success']) {
        echo "✓ Mensagem enviada com sucesso!\n";
        echo "  Aguarde alguns segundos para receber no WhatsApp.\n";
    } else {
        echo "✗ Falha no envio da mensagem.\n";
        if (isset($resultado['response']['message'])) {
            echo "  Erro: " . $resultado['response']['message'] . "\n";
        }
    }

} catch (Exception $e) {
    echo "EXCEÇÃO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n===================================================================\n";
echo "                      FIM DO TESTE                                 \n";
echo "===================================================================\n";
