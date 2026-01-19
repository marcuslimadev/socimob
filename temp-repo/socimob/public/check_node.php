<?php
/**
 * Debug: Verifica instalação do Node.js no servidor
 */

echo "=== VERIFICAÇÃO NODE.JS ===\n\n";

// 1. which node
echo "1. which node:\n";
exec('which node 2>&1', $whichNode, $whichNodeCode);
echo implode("\n", $whichNode) . "\n";
echo "Exit Code: $whichNodeCode\n\n";

// 2. which npm
echo "2. which npm:\n";
exec('which npm 2>&1', $whichNpm, $whichNpmCode);
echo implode("\n", $whichNpm) . "\n";
echo "Exit Code: $whichNpmCode\n\n";

// 3. node --version
echo "3. node --version:\n";
exec('node --version 2>&1', $nodeVersion, $nodeVersionCode);
echo implode("\n", $nodeVersion) . "\n";
echo "Exit Code: $nodeVersionCode\n\n";

// 4. npm --version
echo "4. npm --version:\n";
exec('npm --version 2>&1', $npmVersion, $npmVersionCode);
echo implode("\n", $npmVersion) . "\n";
echo "Exit Code: $npmVersionCode\n\n";

// 5. Procurar em caminhos comuns do cPanel
echo "5. Procurando em caminhos comuns:\n";
$commonPaths = [
    '/usr/local/bin/node',
    '/usr/bin/node',
    '/opt/alt/alt-nodejs16/root/usr/bin/node',
    '/opt/alt/alt-nodejs18/root/usr/bin/node',
    '/opt/alt/alt-nodejs20/root/usr/bin/node',
    '/opt/cpanel/ea-nodejs16/bin/node',
    '/opt/cpanel/ea-nodejs18/bin/node',
    '/opt/cpanel/ea-nodejs20/bin/node',
    '/home/' . get_current_user() . '/.nvm/versions/node/*/bin/node'
];

foreach ($commonPaths as $path) {
    if (strpos($path, '*') !== false) {
        // Glob para NVM
        $found = glob($path);
        if (!empty($found)) {
            foreach ($found as $f) {
                echo "✅ ENCONTRADO: $f\n";
                exec("$f --version 2>&1", $v);
                echo "   Versão: " . implode('', $v) . "\n";
            }
        }
    } else {
        if (file_exists($path)) {
            echo "✅ ENCONTRADO: $path\n";
            exec("$path --version 2>&1", $v);
            echo "   Versão: " . implode('', $v) . "\n";
        } else {
            echo "❌ NÃO EXISTE: $path\n";
        }
    }
}

echo "\n6. Variáveis de ambiente:\n";
echo "PATH: " . getenv('PATH') . "\n";
echo "HOME: " . getenv('HOME') . "\n";
echo "USER: " . get_current_user() . "\n";

echo "\n7. ls /opt/alt/ (CloudLinux):\n";
exec('ls -la /opt/alt/ 2>&1', $optAlt);
echo implode("\n", $optAlt) . "\n";

echo "\n=== FIM ===\n";
