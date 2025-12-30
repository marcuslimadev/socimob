<?php
/**
 * Debug de transcrição de áudio - Acesse via HTTP
 * URL: https://exclusivalarimoveis.com/debug-audio.php
 * DELETAR após uso por segurança!
 */

header('Content-Type: text/plain; charset=utf-8');

echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║     🔍 DEBUG DE TRANSCRIÇÃO DE ÁUDIO                              ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";

$logFile = __DIR__ . '/storage/logs/lumen-' . date('Y-m-d') . '.log';

if (!file_exists($logFile)) {
    echo "❌ Log file não encontrado: $logFile\n";
    exit;
}

$log = file_get_contents($logFile);
$lines = explode("\n", $log);

echo "📊 TOTAL DE LINHAS NO LOG: " . count($lines) . "\n\n";

// Buscar linhas relacionadas a áudio/transcrição
$relevantLines = [];
$inErrorBlock = false;

foreach ($lines as $i => $line) {
    // Detectar início de bloco relevante
    if (stripos($line, 'áudio detectado') !== false ||
        stripos($line, 'transcrição') !== false ||
        stripos($line, 'transcri') !== false ||
        stripos($line, 'ffmpeg') !== false ||
        stripos($line, 'convertendo') !== false ||
        stripos($line, 'openai') !== false ||
        stripos($line, 'whisper') !== false) {
        $relevantLines[] = $line;
        $inErrorBlock = true;
        
        // Pegar próximas 5 linhas se for erro
        if (stripos($line, 'ERROR') !== false || stripos($line, 'ERRO') !== false) {
            for ($j = 1; $j <= 5; $j++) {
                if (isset($lines[$i + $j])) {
                    $relevantLines[] = '    ' . $lines[$i + $j];
                }
            }
        }
    }
}

if (empty($relevantLines)) {
    echo "❌ Nenhuma linha relacionada a áudio/transcrição encontrada.\n\n";
    echo "🔍 Últimas 50 linhas do log:\n";
    echo str_repeat("-", 70) . "\n";
    foreach (array_slice($lines, -50) as $line) {
        echo $line . "\n";
    }
} else {
    echo "✅ Encontradas " . count($relevantLines) . " linhas relevantes.\n\n";
    echo "📝 LOGS DE ÁUDIO/TRANSCRIÇÃO (últimas 100):\n";
    echo str_repeat("=", 70) . "\n";
    
    foreach (array_slice($relevantLines, -100) as $line) {
        echo $line . "\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "🔧 TESTES DE SISTEMA:\n";
echo str_repeat("-", 70) . "\n\n";

// Verificar FFmpeg
echo "1. Verificando FFmpeg:\n";
$ffmpegPaths = [
    __DIR__ . '/bin/ffmpeg',
    __DIR__ . '/ffmpeg',
    getenv('HOME') . '/bin/ffmpeg',
    '/usr/bin/ffmpeg',
    '/usr/local/bin/ffmpeg'
];

$ffmpegFound = false;
foreach ($ffmpegPaths as $path) {
    $exists = file_exists($path);
    $executable = $exists && is_executable($path);
    
    echo "   - $path: ";
    if (!$exists) {
        echo "❌ Não existe\n";
    } elseif (!$executable) {
        echo "⚠️  Existe mas não é executável\n";
    } else {
        echo "✅ OK\n";
        $ffmpegFound = $path;
    }
}

if ($ffmpegFound) {
    echo "\n   🎯 FFmpeg encontrado em: $ffmpegFound\n";
    echo "   📋 Versão:\n";
    $output = shell_exec("$ffmpegFound -version 2>&1 | head -n 1");
    echo "      " . $output . "\n";
} else {
    echo "\n   ❌ FFmpeg NÃO ENCONTRADO em nenhum caminho!\n";
}

echo "\n2. Diretório de trabalho:\n";
echo "   - Base path: " . __DIR__ . "\n";
echo "   - Storage: " . (__DIR__ . '/storage') . "\n";
echo "   - Temp: " . (__DIR__ . '/storage/app/temp') . "\n";

$tempDir = __DIR__ . '/storage/app/temp';
if (!is_dir($tempDir)) {
    echo "   ⚠️  Diretório temp não existe!\n";
} else {
    $writable = is_writable($tempDir);
    echo "   " . ($writable ? '✅' : '❌') . " Temp dir " . ($writable ? 'gravável' : 'não gravável') . "\n";
}

echo "\n3. Variáveis de ambiente:\n";
echo "   - HOME: " . (getenv('HOME') ?: '[não definido]') . "\n";
echo "   - PHP_OS_FAMILY: " . PHP_OS_FAMILY . "\n";

echo "\n" . str_repeat("=", 70) . "\n";
echo "⚠️  DELETAR ESTE ARQUIVO APÓS USO POR SEGURANÇA!\n";
echo "   Execute: rm " . __FILE__ . "\n";
echo str_repeat("=", 70) . "\n";

// Auto-deletar após 1 hora
$age = time() - filemtime(__FILE__);
if ($age > 3600) {
    @unlink(__FILE__);
    echo "\n✅ Arquivo auto-deletado após 1 hora.\n";
}
