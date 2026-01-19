<?php
$baseDir = '/home/u815655858/domains/lojadaesquina.store/public_html';
$svelteDir = $baseDir . '/portal-svelte';

function headList($path, $n = 50) {
    if (!is_dir($path)) {
        echo "❌ Diretório não existe: $path\n";
        return;
    }
    $files = scandir($path);
    $i = 0;
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $full = "$path/$f";
        $type = is_dir($full) ? 'dir' : (is_link($full) ? 'link' : 'file');
        $size = is_file($full) ? filesize($full) : 0;
        $exec = is_executable($full) ? 'exec' : '-';
        echo "$f ($type,$exec,$size bytes)\n";
        $i++;
        if ($i >= $n) break;
    }
}

echo "=== CHECK ESBUILD ===\n\n";

echo "1. .bin contents:\n";
headList("$svelteDir/node_modules/.bin", 50);

echo "\n2. esbuild candidates:\n";
$candidates = [
    "$svelteDir/node_modules/@esbuild/linux-x64/bin/esbuild",
    "$svelteDir/node_modules/esbuild-linux-64/bin/esbuild",
    "$svelteDir/node_modules/esbuild/bin/esbuild",
];
foreach ($candidates as $c) {
    echo ($c) . ' => ' . (file_exists($c) ? '✅' : '❌') . "\n";
}

echo "\n3. esbuild version with env (if binary exists):\n";
$env = "HOME=$baseDir PATH=/opt/alt/alt-nodejs20/root/usr/bin:/bin:/usr/bin";
$node = '/opt/alt/alt-nodejs20/root/usr/bin/node';
foreach ($candidates as $c) {
    if (file_exists($c)) {
        $out=[];$code=0;
        exec("cd $svelteDir && $env ESBUILD_BINARY_PATH=$c $node -e \"console.log(require('esbuild').version)\" 2>&1", $out, $code);
        echo basename($c) . ": code=$code => " . implode(" ", $out) . "\n";
    }
}

echo "\n4. npm list esbuild/vite:\n";
$out=[];exec("cd $svelteDir && $env npm list esbuild vite 2>&1", $out);echo implode("\n", $out) . "\n";

echo "\n5. Teste direto do build chamando esbuild CLI (dry run --version):\n";
foreach ($candidates as $c) {
    if (file_exists($c)) {
        $out=[];$code=0;
        exec("cd $svelteDir && $env ESBUILD_BINARY_PATH=$c $node $c --version 2>&1", $out, $code);
        echo basename($c) . " -> code=$code => " . implode(" ", $out) . "\n";
    }
}

$path = "/opt/alt/alt-nodejs20/root/usr/bin:$svelteDir/node_modules/.bin:/bin:/usr/bin";
echo "\n6. PATH atual do deploy build:\n$path\n";

echo "\n=== FIM ===\n";
