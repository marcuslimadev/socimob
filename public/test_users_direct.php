<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Direct Test - Users Query</h1>";

try {
    require_once __DIR__ . '/../bootstrap/app.php';
    
    // Simular usuário autenticado
    $authUser = app('db')->table('users')->where('id', 2)->first(); // Admin Exclusiva
    
    echo "<h2>User autenticado:</h2>";
    echo "<pre>";
    print_r($authUser);
    echo "</pre>";
    
    echo "<h2>Tentando executar a query da rota:</h2>";
    
    // Executar exatamente a mesma query da rota
    $users = app('db')->table('users')
        ->where('tenant_id', $authUser->tenant_id)
        ->select('id', 'name', 'email', 'telefone', 'role', 'is_active as ativo', 'created_at')
        ->orderBy('created_at', 'desc')
        ->get();
    
    echo "<h2>Resultado:</h2>";
    echo "<pre>";
    print_r($users);
    echo "</pre>";
    
    echo "<h2>JSON Response:</h2>";
    echo "<pre>";
    echo json_encode(['data' => $users], JSON_PRETTY_PRINT);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red'>ERRO:</h2>";
    echo "<pre style='color: red'>";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack Trace:\n" . $e->getTraceAsString();
    echo "</pre>";
}
