<?php
/**
 * Endpoint temporário para ler logs (APENAS DESENVOLVIMENTO)
 * Deletar após uso!
 */

// Verificar secret
$secret = $_GET['secret'] ?? '';
if ($secret !== 'ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8=') {
    http_response_code(401);
    die('Unauthorized');
}

$logFile = __DIR__ . '/storage/logs/lumen-' . date('Y-m-d') . '.log';

header('Content-Type: text/plain; charset=utf-8');

if (!file_exists($logFile)) {
    echo "❌ Arquivo de log não encontrado: " . basename($logFile) . "\n";
    echo "Data atual do servidor: " . date('Y-m-d H:i:s') . "\n";
    exit;
}

$lines = file($logFile);
$totalLines = count($lines);

// Filtrar linhas do webhook
$webhookLines = [];
foreach ($lines as $i => $line) {
    if (stripos($line, 'WEBHOOK') !== false || 
        stripos($line, 'WhatsApp') !== false ||
        stripos($line, 'Twilio') !== false ||
        stripos($line, 'Conversa') !== false ||
        stripos($line, 'Lead') !== false ||
        stripos($line, 'Mensagem') !== false ||
        stripos($line, 'ERROR') !== false) {
        $webhookLines[] = "[Linha $i] $line";
    }
}

echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║         📋 LOGS DE WEBHOOK - " . date('Y-m-d H:i:s') . "                  ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";

echo "📁 Arquivo: " . basename($logFile) . "\n";
echo "📊 Total de linhas: $totalLines\n";
echo "🔍 Linhas relacionadas ao webhook: " . count($webhookLines) . "\n\n";

if (count($webhookLines) > 0) {
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "ÚLTIMAS 100 ENTRADAS DE WEBHOOK:\n";
    echo "═══════════════════════════════════════════════════════════════════\n\n";
    
    foreach (array_slice($webhookLines, -100) as $line) {
        echo $line;
    }
} else {
    echo "⚠️ Nenhuma entrada de webhook encontrada.\n\n";
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "ÚLTIMAS 50 LINHAS DO LOG:\n";
    echo "═══════════════════════════════════════════════════════════════════\n\n";
    
    foreach (array_slice($lines, -50) as $line) {
        echo $line;
    }
}
