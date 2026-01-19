<?php
/**
 * Debug: Verifica estado do Git no servidor
 */

echo "=== VERIFICAÇÃO GIT ===\n\n";

$baseDir = dirname(__DIR__);

echo "1. git status:\n";
exec("cd $baseDir && git status 2>&1", $status);
echo implode("\n", $status) . "\n\n";

echo "2. git log (último commit):\n";
exec("cd $baseDir && git log -1 --oneline 2>&1", $log);
echo implode("\n", $log) . "\n\n";

echo "3. git remote -v:\n";
exec("cd $baseDir && git remote -v 2>&1", $remote);
echo implode("\n", $remote) . "\n\n";

echo "4. git branch:\n";
exec("cd $baseDir && git branch 2>&1", $branch);
echo implode("\n", $branch) . "\n\n";

echo "5. git fetch + git pull:\n";
exec("cd $baseDir && git fetch origin 2>&1", $fetch);
echo implode("\n", $fetch) . "\n";
exec("cd $baseDir && git pull origin master 2>&1", $pull, $pullCode);
echo implode("\n", $pull) . "\n";
echo "Exit Code: $pullCode\n\n";

echo "6. Conteúdo do DeployController (linha 145-155):\n";
$controller = file_get_contents("$baseDir/app/Http/Controllers/DeployController.php");
$lines = explode("\n", $controller);
for ($i = 144; $i <= 154; $i++) {
    echo ($i+1) . ": " . ($lines[$i] ?? '') . "\n";
}

echo "\n=== FIM ===\n";
