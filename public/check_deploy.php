<?php
$controller = file_get_contents('/home/u815655858/domains/lojadaesquina.store/public_html/app/Http/Controllers/DeployController.php');
$lines = explode("\n", $controller);
echo "Linhas 152-170 do DeployController:\n\n";
for ($i = 151; $i <= 169; $i++) {
    echo ($i+1) . ": " . ($lines[$i] ?? '') . "\n";
}
