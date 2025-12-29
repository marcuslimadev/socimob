<?php
/**
 * Endpoint TEMPORÁRIO para visualizar logs de webhook
 * REMOVER após diagnóstico!
 */

// Token de segurança simples
$token = $_GET['token'] ?? '';
if ($token !== 'temp-debug-2025') {
    http_response_code(403);
    die('Access denied');
}

$logFile = __DIR__ . '/../storage/logs/lumen-' . date('Y-m-d') . '.log';

if (!file_exists($logFile)) {
    http_response_code(404);
    die('Log file not found: ' . $logFile);
}

// Ler arquivo
$content = file_get_contents($logFile);
$lines = explode("\n", $content);

// Filtrar linhas relevantes
$filtered = [];
foreach ($lines as $line) {
    if (stripos($line, 'WEBHOOK') !== false || 
        stripos($line, 'WhatsApp') !== false ||
        stripos($line, 'Twilio') !== false ||
        stripos($line, 'Conversa') !== false ||
        stripos($line, 'Lead') !== false ||
        stripos($line, 'Mensagem') !== false ||
        stripos($line, 'ERROR') !== false) {
        $filtered[] = $line;
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "═══════════════════════════════════════════════════════════════\n";
echo "LOGS DO WEBHOOK - " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

if (empty($filtered)) {
    echo "⚠️ Nenhuma entrada de webhook encontrada.\n\n";
    echo "Últimas 50 linhas do log:\n\n";
    foreach (array_slice($lines, -50) as $line) {
        echo $line . "\n";
    }
} else {
    echo "📱 Entradas de webhook (últimas 100):\n\n";
    foreach (array_slice($filtered, -100) as $line) {
        echo $line . "\n";
    }
}
