<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

$tenants = $app->make('db')->table('tenants')->get();
echo "Tenants no banco:" . PHP_EOL;
foreach ($tenants as $t) {
    echo "ID: " . $t->id . PHP_EOL;
    echo "  Name: " . $t->name . PHP_EOL;
    echo "  Domain: " . ($t->domain ?: 'NÃO DEFINIDO') . PHP_EOL;
    echo PHP_EOL;
}

// Atualizar domain do tenant 1 para exclusivalarimoveis.com
echo "Atualizando tenant 1 com domain = exclusivalarimoveis.com..." . PHP_EOL;
$app->make('db')->table('tenants')
    ->where('id', 1)
    ->update(['domain' => 'exclusivalarimoveis.com']);

echo "✓ Feito!" . PHP_EOL;

// Verificar
$tenant = $app->make('db')->table('tenants')->where('id', 1)->first();
echo "\nTenant 1 agora: " . ($tenant->domain ?: 'NÃO DEFINIDO') . PHP_EOL;
