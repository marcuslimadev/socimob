<?php
$svelteDir = '/home/u815655858/domains/lojadaesquina.store/public_html/portal-svelte';

echo "=== DEBUG NPM INSTALL ===\n\n";

echo "1. package.json existe?\n";
$packageJson = "$svelteDir/package.json";
echo file_exists($packageJson) ? "✅ SIM\n" : "❌ NÃO\n";
echo "\n";

echo "2. Conteúdo do package.json:\n";
if (file_exists($packageJson)) {
    echo file_get_contents($packageJson);
} else {
    echo "Arquivo não existe!";
}
echo "\n\n";

echo "3. Executar npm install com verbose:\n";
$nodePath = '/opt/alt/alt-nodejs20/root/usr/bin/node';
$npmPath = '/opt/alt/alt-nodejs20/root/usr/bin/npm';
$homeDir = '/home/u815655858/domains/lojadaesquina.store/public_html';
$env = "HOME=$homeDir NODE_ENV=production PATH=/opt/alt/alt-nodejs20/root/usr/bin:/bin:/usr/bin";

exec("cd $svelteDir && $env $npmPath install --verbose 2>&1", $output, $code);
echo implode("\n", $output);
echo "\n\nExit Code: $code\n";

echo "\n4. Verificar node_modules após install:\n";
exec("ls -la $svelteDir/node_modules/ | head -20", $nodeModulesList);
echo implode("\n", $nodeModulesList);

echo "\n\n5. Verificar .bin após install:\n";
exec("ls -la $svelteDir/node_modules/.bin/", $binList);
echo implode("\n", $binList);

echo "\n\n=== FIM ===\n";
