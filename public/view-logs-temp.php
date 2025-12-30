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

$logDir = __DIR__ . '/../storage/logs/';

// Tentar hoje e ontem (timezone pode ser diferente)
$possibleDates = [
    date('Y-m-d'),
    date('Y-m-d', strtotime('-1 day')),
    date('Y-m-d', strtotime('-2 days'))
];

$logFile = null;
foreach ($possibleDates as $date) {
    $file = $logDir . 'lumen-' . $date . '.log';
    if (file_exists($file)) {
        $logFile = $file;
        break;
    }
}

// Se não encontrou, pegar o mais recente
if (!$logFile && is_dir($logDir)) {
    $files = glob($logDir . 'lumen-*.log');
    if (!empty($files)) {
        rsort($files); // Mais recente primeiro
        $logFile = $files[0];
    }
}

if (!$logFile || !file_exists($logFile)) {
    http_response_code(404);
    echo "Log files not found.\n\n";
    echo "Searched in: $logDir\n";
    echo "Attempted dates: " . implode(', ', $possibleDates) . "\n";
    if (is_dir($logDir)) {
        $files = glob($logDir . 'lumen-*.log');
        echo "\nAvailable logs:\n";
        foreach ($files as $f) {
            echo "  - " . basename($f) . " (" . date('Y-m-d H:i:s', filemtime($f)) . ")\n";
        }
    }
    die();
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
echo "LOGS DO WEBHOOK - " . basename($logFile) . "\n";
echo "Última modificação: " . date('Y-m-d H:i:s', filemtime($logFile)) . "\n";
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
