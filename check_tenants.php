<?php
require 'bootstrap/app.php';

try {
    $tenants = \App\Models\Tenant::all();
    echo "Total tenants: " . $tenants->count() . PHP_EOL;

    foreach($tenants as $t) {
        echo "ID: " . $t->id . ", Domain: " . $t->domain . ", Name: " . $t->name . PHP_EOL;
    }

    if ($tenants->count() == 0) {
        echo "Nenhum tenant encontrado. Criando tenant de teste..." . PHP_EOL;

        $tenant = new \App\Models\Tenant();
        $tenant->name = 'Exclusiva Imóveis';
        $tenant->domain = '127.0.0.1';
        $tenant->save();

        echo "Tenant criado: ID " . $tenant->id . ", Domain: " . $tenant->domain . PHP_EOL;
    }

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . PHP_EOL;
}