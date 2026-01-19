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

// Permitir especificar data ou listar arquivos
$date = $_GET['date'] ?? date('Y-m-d');
$action = $_GET['action'] ?? 'read';

if ($action === 'list') {
    $logDir = __DIR__ . '/../storage/logs/';
    $files = glob($logDir . 'lumen-*.log');
    echo "📁 Arquivos de log disponíveis:\n\n";
    foreach ($files as $file) {
        echo basename($file) . " (" . human_filesize(filesize($file)) . ")\n";
    }
    exit;
}

$logFile = __DIR__ . '/../storage/logs/lumen-' . $date . '.log';

function human_filesize($bytes, $decimals = 2) {
    $size = array('B','KB','MB','GB','TB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $size[$factor];
}

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
