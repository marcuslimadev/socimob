<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Users API</h1>";

// Simular chamada à rota
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/admin/users';

// Verificar se existe Authorization header
$headers = getallheaders();
echo "<h2>Headers recebidos:</h2>";
echo "<pre>";
print_r($headers);
echo "</pre>";

// Tentar carregar o app
require_once __DIR__ . '/../bootstrap/app.php';

try {
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    
    echo "<h2>App carregado com sucesso</h2>";
    
    // Testar acesso direto ao banco
    $db = app('db');
    echo "<h2>Conexão DB:</h2>";
    $users = $db->table('users')->limit(5)->get();
    echo "<pre>";
    print_r($users);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h2>ERRO:</h2>";
    echo "<pre style='color: red'>";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Stack:\n" . $e->getTraceAsString();
    echo "</pre>";
}
