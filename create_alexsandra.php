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
    
    // Verificar se o tenant 1 existe
    $stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE id = 1");
    $stmt->execute();
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        echo "❌ Erro: Tenant ID 1 não encontrado no banco de dados\n";
        exit(1);
    }
    
    echo "✅ Tenant encontrado: {$tenant['name']}\n";
    
    // Verificar se o usuário já existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['alexsandra@exclusivalarimoveis.com']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "⚠️  Usuário já existe. Atualizando senha...\n";
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, role = ?, tenant_id = ?, is_active = 1, updated_at = NOW()
            WHERE email = ?
        ");
        $password = password_hash('password', PASSWORD_BCRYPT);
        $result = $stmt->execute([
            $password,
            'admin',
            1,
            'alexsandra@exclusivalarimoveis.com'
        ]);
    } else {
        // Create new user directly
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, tenant_id, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $password = password_hash('password', PASSWORD_BCRYPT);
        $result = $stmt->execute([
            'Alexsandra Silva',
            'alexsandra@exclusivalarimoveis.com',
            $password,
            'admin',
            1,
            1
        ]);
    }
    
    if ($result) {
        echo "✅ Usuário criado/atualizado com sucesso!\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📋 CREDENCIAIS DE ACESSO\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "  👤 Nome: Alexsandra Silva\n";
        echo "  📧 Email: alexsandra@exclusivalarimoveis.com\n";
        echo "  🔑 Senha: password\n";
        echo "  🏢 Tenant: {$tenant['name']} (ID: 1)\n";
        echo "  👔 Perfil: Admin de Imobiliária\n";
        echo "  ✅ Status: Ativo\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        echo "🌐 Acesse: http://127.0.0.1:8000/app/login.html\n\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
