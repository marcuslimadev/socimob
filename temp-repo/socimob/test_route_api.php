<?php
// Test básico de rotas
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

// Simular requisição
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/login';
$_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

// Criar input stream
file_put_contents('php://input', json_encode([
    'email' => 'admin@exclusiva.com',
    'password' => 'password'
]));

try {
    $response = $app->handle(
        Illuminate\Http\Request::capture()
    );
    
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content: " . $response->getContent() . "\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
