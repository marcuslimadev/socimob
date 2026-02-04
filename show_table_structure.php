<?php
require __DIR__ . '/bootstrap/app.php';

echo "=== CONVERSAS ===\n";
$columns = DB::select('SHOW COLUMNS FROM conversas');
foreach($columns as $col) {
    echo $col->Field . "\n";
}
