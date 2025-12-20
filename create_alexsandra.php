<?php

require __DIR__ . '/vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(dirname(__DIR__)))->bootstrap();

$app = new Laravel\Lumen\Application(dirname(__DIR__));

$app->withFacades();
$app->withEloquent();

$app->configure('app');
$app->configure('database');
$app->configure('cache');
$app->configure('session');

require __DIR__ . '/config/database.php';

try {
    // Create the PDO connection
    $pdo = new PDO(
        'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_DATABASE', 'exclusiva'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✅ Conexão com banco de dados OK\n";
    
    // Create new user directly
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, is_active, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $password = password_hash('Senha@123', PASSWORD_BCRYPT);
    $result = $stmt->execute([
        'Alexsandra Silva',
        'alexsandra@exclusiva.com.br',
        $password,
        'admin',
        1
    ]);
    
    if ($result) {
        echo "✅ Usuário criado com sucesso!\n";
        echo "📋 Detalhes:\n";
        echo "  - Nome: Alexsandra Silva\n";
        echo "  - Email: alexsandra@exclusiva.com.br\n";
        echo "  - Senha: Senha@123\n";
        echo "  - Perfil: admin\n";
        echo "  - Status: Ativo\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
