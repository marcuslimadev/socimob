<?php

require_once 'bootstrap/app.php';

try {
    $db = app('db');
    $columns = $db->getSchemaBuilder()->getColumnListing('imo_properties');
    echo "Colunas da tabela imo_properties:\n";
    foreach($columns as $col) {
        echo "- $col\n";
    }
    
    echo "\nVerificando se 'bairro' existe: " . (in_array('bairro', $columns) ? 'SIM' : 'NÃO') . "\n";
    echo "Verificando se 'cep' existe: " . (in_array('cep', $columns) ? 'SIM' : 'NÃO') . "\n";
    
} catch(Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}