<?php
require __DIR__ . '/bootstrap/app.php';

try {
    $db = app('db')->connection();
    
    echo "═══════════════════════════════════════════════════════════════\n";
    echo " LEADS TABLE SCHEMA\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    $columns = $db->select("DESCRIBE leads");
    
    foreach ($columns as $column) {
        echo "Column: {$column->Field}\n";
        echo "  Type: {$column->Type}\n";
        echo "  Null: {$column->Null}\n";
        echo "  Default: {$column->Default}\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
