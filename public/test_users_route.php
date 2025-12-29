<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Admin Users Route</h1>";

// Simular request autenticado
$token = 'MXwxNzM1NDU0MTExfGFjM2Y0NzEzMDY0MzYyOTNmOTdmMDc3OTE1MGY1MTcz'; // Token do admin
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/admin/users';

echo "<p>Token usado: " . substr($token, 0, 30) . "...</p>";

try {
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    
    // Criar request
    $request = Illuminate\Http\Request::capture();
    $request->headers->set('Authorization', 'Bearer ' . $token);
    
    echo "<h2>Request criado</h2>";
    echo "<pre>";
    echo "Method: " . $request->method() . "\n";
    echo "Path: " . $request->path() . "\n";
    echo "Authorization: " . $request->header('Authorization') . "\n";
    echo "</pre>";
    
    // Processar request
    $response = $app->handle($request);
    
    echo "<h2>Response</h2>";
    echo "<pre>";
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Headers:\n";
    foreach ($response->headers->all() as $key => $values) {
        echo "  $key: " . implode(', ', $values) . "\n";
    }
    echo "\nContent Type: " . $response->headers->get('Content-Type') . "\n";
    echo "\nRaw Content:\n";
    echo $response->getContent();
    echo "\n\nDecoded Content:\n";
    print_r(json_decode($response->getContent(), true));
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red'>ERRO:</h2>";
    echo "<pre style='color: red'>";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack Trace:\n" . $e->getTraceAsString();
    echo "</pre>";
}
