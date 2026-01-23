<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

// Simular acesso a exclusivalarimoveis.com
$_SERVER['HTTP_HOST'] = 'exclusivalarimoveis.com';

// Tentar resolver tenant
$middleware = new \App\Http\Middleware\ResolveTenant();
$request = new \Illuminate\Http\Request();
$request->server->set('HTTP_HOST', 'exclusivalarimoveis.com');

echo "Testando tenant resolution para exclusivalarimoveis.com" . PHP_EOL;

// Ver todos os tenants
$tenants = $app->make('db')->table('tenants')->get();
echo "\nTenants no banco:" . PHP_EOL;
foreach ($tenants as $t) {
    echo "  - ID: " . $t->id . ", Nome: " . $t->name . PHP_EOL;
}

// Ver usuário marcus
$user = $app->make('db')->table('users')->where('email', 'marcus.lima@hotmail.com.br')->first();
if ($user) {
    echo "\nUsuário marcus.lima@hotmail.com.br:" . PHP_EOL;
    echo "  Tenant ID: " . $user->tenant_id . PHP_EOL;
    echo "  Role: " . $user->role . PHP_EOL;
}

// Por enquanto, vamos colocar marcus no tenant 1 (que parece ser "Exclusiva Imóveis")
echo "\nAtualizando marcus para tenant_id = 1..." . PHP_EOL;
$app->make('db')->table('users')
    ->where('email', 'marcus.lima@hotmail.com.br')
    ->update(['tenant_id' => 1]);

echo "✓ Feito!" . PHP_EOL;
