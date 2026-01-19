<?php
header('Content-Type: text/plain; charset=utf-8');

$logFile = __DIR__ . '/../storage/logs/lumen-' . date('Y-m-d') . '.log';

echo "═══════════════════════════════════════════════════════════\n";
echo "📋 LOGS DE ÁUDIO/TRANSCRIÇÃO - " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════\n\n";

if (!file_exists($logFile)) {
    echo "❌ Log file not found: $logFile\n";
    
    // Listar arquivos disponíveis
    $dir = dirname($logFile);
    echo "\n📁 Arquivos disponíveis em $dir:\n";
    foreach (glob($dir . '/lumen-*.log') as $file) {
        echo "  - " . basename($file) . " (" . number_format(filesize($file)/1024, 2) . " KB)\n";
    }
    exit;
}

$log = file_get_contents($logFile);
$lines = explode(PHP_EOL, $log);

echo "📊 Total de linhas no log: " . count($lines) . "\n\n";

// Buscar linhas relacionadas a áudio/transcrição
$audioLines = [];
foreach ($lines as $line) {
    if (stripos($line, 'áudio') !== false || 
        stripos($line, 'audio') !== false || 
        stripos($line, 'transcri') !== false || 
        stripos($line, 'ffmpeg') !== false ||
        stripos($line, 'ogg') !== false ||
        stripos($line, 'mp3') !== false ||
        stripos($line, 'MediaUrl') !== false ||
        stripos($line, 'media_url') !== false ||
        stripos($line, 'MediaType') !== false) {
        $audioLines[] = $line;
    }
}

if (empty($audioLines)) {
    echo "❌ Nenhuma linha relacionada a áudio encontrada.\n\n";
    echo "🔍 Últimas 30 linhas do log:\n";
    echo str_repeat("-", 70) . "\n";
    foreach (array_slice($lines, -30) as $line) {
        echo $line . "\n";
    }
} else {
    echo "✅ Encontradas " . count($audioLines) . " linhas relacionadas a áudio.\n\n";
    echo "📝 Últimas 50 linhas:\n";
    echo str_repeat("-", 70) . "\n";
    foreach (array_slice($audioLines, -50) as $line) {
        echo $line . "\n";
    }
}

// Deletar este script após 1 hora
$scriptAge = time() - filemtime(__FILE__);
if ($scriptAge > 3600) {
    @unlink(__FILE__);
}