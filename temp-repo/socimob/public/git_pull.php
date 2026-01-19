<?php
$baseDir = '/home/u815655858/domains/lojadaesquina.store/public_html';

echo "=== GIT PULL ===\n\n";

echo "1. git fetch:\n";
exec("cd $baseDir && git fetch origin 2>&1", $fetchOutput);
echo implode("\n", $fetchOutput) . "\n\n";

echo "2. git pull:\n";
exec("cd $baseDir && git pull origin master 2>&1", $pullOutput, $pullCode);
echo implode("\n", $pullOutput) . "\n";
echo "Exit Code: $pullCode\n\n";

echo "3. git log (último commit):\n";
exec("cd $baseDir && git log -1 --oneline 2>&1", $logOutput);
echo implode("\n", $logOutput) . "\n\n";

echo "=== FIM ===\n";
