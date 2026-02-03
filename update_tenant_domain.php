<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

try {
    $tenant = \App\Models\Tenant::find(1);
    
    if (!$tenant) {
        echo "Tenant ID 1 não encontrado\n";
        exit(1);
    }
    
    echo "Domínio atual: " . $tenant->domain . "\n";
    
    $tenant->domain = 'exclusivalarimoveis.com';
    $tenant->save();
    
    echo "Domínio atualizado para: " . $tenant->domain . "\n";
    echo "Sucesso!\n";
    
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
