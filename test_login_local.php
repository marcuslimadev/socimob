<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

// Simular request para exclusivalarimoveis.com
$_SERVER['HTTP_HOST'] = 'exclusivalarimoveis.com';

// Requisição de teste
$request = new \Illuminate\Http\Request();
$request->server->set('HTTP_HOST', 'exclusivalarimoveis.com');
$request->request->add([
    'email' => 'marcus.lima@hotmail.com.br',
    'password' => 'password'
]);

echo "Testando login..." . PHP_EOL;
echo "Host: " . $request->getHost() . PHP_EOL;

// Carregar middleware de tenant
$middleware = new \App\Http\Middleware\ResolveTenant();

// Callback fake que retorna a response
$called = false;
$middleware->handle($request, function($req) use (&$called) {
    $called = true;
    return "OK";
});

// Ver se tenant foi resolvido
if (app()->bound('tenant')) {
    $tenant = app('tenant');
    echo "✓ Tenant resolvido: ID " . $tenant->id . ", Domain: " . $tenant->domain . PHP_EOL;
} else {
    echo "✗ Tenant NÃO resolvido!" . PHP_EOL;
}

// Testar controller
$controller = new \App\Http\Controllers\AuthController();
try {
    $response = $controller->login($request);
    echo PHP_EOL . "Response: " . $response->getContent() . PHP_EOL;
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
