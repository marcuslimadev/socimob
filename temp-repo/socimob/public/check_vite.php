<?php
$baseDir = '/home/u815655858/domains/lojadaesquina.store/public_html';
$svelteDir = $baseDir . '/portal-svelte';

echo "=== VERIFICAÇÃO VITE ===\n\n";

echo "1. Diretório Svelte existe?\n";
echo is_dir($svelteDir) ? "✅ SIM: $svelteDir\n" : "❌ NÃO\n";
echo "\n";

echo "2. node_modules existe?\n";
$nodeModules = "$svelteDir/node_modules";
echo is_dir($nodeModules) ? "✅ SIM: $nodeModules\n" : "❌ NÃO\n";
echo "\n";

echo "3. .bin existe?\n";
$binDir = "$nodeModules/.bin";
echo is_dir($binDir) ? "✅ SIM: $binDir\n" : "❌ NÃO\n";
echo "\n";

echo "4. Conteúdo de .bin:\n";
if (is_dir($binDir)) {
    $files = scandir($binDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $fullPath = "$binDir/$file";
            $type = is_link($fullPath) ? 'link' : (is_file($fullPath) ? 'file' : 'dir');
            $exec = is_executable($fullPath) ? '✅ exec' : '❌ no-exec';
            echo "   $file ($type, $exec)\n";
            
            if ($file === 'vite' && is_link($fullPath)) {
                echo "      → " . readlink($fullPath) . "\n";
            }
        }
    }
} else {
    echo "   Diretório não existe\n";
}
echo "\n";

echo "5. Vite path esperado:\n";
$vitePath = "$svelteDir/node_modules/.bin/vite";
echo "   $vitePath\n";
echo "   Existe? " . (file_exists($vitePath) ? "✅ SIM" : "❌ NÃO") . "\n";
echo "   Executável? " . (is_executable($vitePath) ? "✅ SIM" : "❌ NÃO") . "\n";
echo "\n";

echo "6. Verificar vite via npm:\n";
exec("cd $svelteDir && /opt/alt/alt-nodejs20/root/usr/bin/npm list vite 2>&1", $npmList);
echo implode("\n", $npmList) . "\n";
echo "\n";

echo "7. Teste manual de execução:\n";
$nodePath = '/opt/alt/alt-nodejs20/root/usr/bin/node';
$env = "HOME=/home/u815655858/domains/lojadaesquina.store/public_html NODE_ENV=production PATH=/opt/alt/alt-nodejs20/root/usr/bin:$svelteDir/node_modules/.bin:/bin:/usr/bin";
exec("cd $svelteDir && $env $nodePath $vitePath --version 2>&1", $viteTest, $viteTestCode);
echo implode("\n", $viteTest) . "\n";
echo "Exit Code: $viteTestCode\n";

echo "\n=== FIM ===\n";
