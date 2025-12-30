<?php
/**
 * Ver últimas requisições recebidas nos webhooks
 */

$token = $_GET['token'] ?? '';
if ($token !== 'temp-debug-2025') {
    http_response_code(403);
    die('Access denied');
}

header('Content-Type: text/plain; charset=utf-8');
echo "═══════════════════════════════════════════════════════════════\n";
echo "DIAGNÓSTICO DE WEBHOOK - " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Verificar log de teste
$testLog = __DIR__ . '/../storage/logs/webhook-test-' . date('Y-m-d') . '.log';
if (file_exists($testLog)) {
    echo "⚠️ WEBHOOK-TEST.PHP ESTÁ SENDO CHAMADO!\n";
    echo "Isso está causando o 'OK' no WhatsApp.\n\n";
    echo "Últimas chamadas:\n";
    echo str_repeat("-", 80) . "\n";
    $content = file_get_contents($testLog);
    $lines = explode("\n", $content);
    foreach (array_slice($lines, -30) as $line) {
        echo $line . "\n";
    }
} else {
    echo "✅ webhook-test.php não foi chamado hoje.\n\n";
}

// Verificar log principal
$mainLog = __DIR__ . '/../storage/logs/lumen-' . date('Y-m-d') . '.log';
if (file_exists($mainLog)) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "LOG PRINCIPAL - Últimas entradas de webhook:\n";
    echo str_repeat("=", 80) . "\n\n";
    
    $content = file_get_contents($mainLog);
    $lines = explode("\n", $content);
    $webhookLines = [];
    
    foreach ($lines as $line) {
        if (stripos($line, 'WEBHOOK') !== false ||
            stripos($line, 'WhatsApp') !== false ||
            stripos($line, 'Twilio') !== false ||
            stripos($line, 'Status callback') !== false) {
            $webhookLines[] = $line;
        }
    }
    
    foreach (array_slice($webhookLines, -20) as $line) {
        echo $line . "\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "CONFIGURAÇÃO CORRETA NA TWILIO:\n";
echo str_repeat("=", 80) . "\n";
echo "Inbound Messages: https://exclusivalarimoveis.com/webhook/whatsapp\n";
echo "Status Callback:  https://exclusivalarimoveis.com/webhook/whatsapp/status\n";
echo "\n❌ NÃO use: /webhook-test.php (apenas para diagnóstico)\n";
