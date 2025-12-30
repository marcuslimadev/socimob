<?php
/**
 * Teste de execução do FFmpeg via web
 * URL: https://exclusivalarimoveis.com/test-ffmpeg.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║     🔧 TESTE DE FFMPEG VIA WEB                                    ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";

// Verificar funções desabilitadas
$disabled = ini_get('disable_functions');
echo "🔍 Funções desabilitadas:\n";
echo $disabled ? $disabled : '   [nenhuma]';
echo "\n\n";

// Testar diferentes caminhos
$paths = [
    '/home/u815655858/domains/lojadaesquina.store/public_html/bin/ffmpeg',
    __DIR__ . '/bin/ffmpeg',
    './bin/ffmpeg',
    'bin/ffmpeg'
];

foreach ($paths as $path) {
    echo "📍 Testando: $path\n";
    echo "   Existe: " . (file_exists($path) ? 'SIM' : 'NÃO') . "\n";
    
    if (file_exists($path)) {
        echo "   Permissões: " . substr(sprintf('%o', fileperms($path)), -4) . "\n";
        echo "   Executável (is_executable): " . (is_executable($path) ? 'SIM' : 'NÃO') . "\n";
        
        // Testar exec()
        echo "   Testando exec()...\n";
        $output = [];
        $returnCode = 0;
        exec("$path -version 2>&1", $output, $returnCode);
        echo "   Return code: $returnCode\n";
        echo "   Output: " . (empty($output) ? '[vazio]' : implode("\n   ", array_slice($output, 0, 3))) . "\n";
        
        // Testar shell_exec()
        echo "   Testando shell_exec()...\n";
        $result = shell_exec("$path -version 2>&1");
        echo "   Resultado: " . ($result ? substr($result, 0, 100) . '...' : '[vazio]') . "\n";
    }
    
    echo "\n";
}

echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║     📊 INFORMAÇÕES DO SISTEMA                                     ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";

echo "PHP Version: " . phpversion() . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "User: " . get_current_user() . "\n";
echo "UID: " . getmyuid() . "\n";
echo "GID: " . getmygid() . "\n";
echo "Working dir: " . getcwd() . "\n";
echo "Open basedir: " . (ini_get('open_basedir') ?: '[não configurado]') . "\n";
echo "Safe mode: " . (ini_get('safe_mode') ? 'ON' : 'OFF') . "\n";

// Auto-deletar após uso
$age = time() - filemtime(__FILE__);
if ($age > 3600) {
    @unlink(__FILE__);
}
