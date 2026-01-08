<?php
/**
 * Listar todos os usuários - endpoint temporário
 */

header('Content-Type: text/plain; charset=utf-8');

// Conexão com banco (usando .env se disponível)
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    $host = $env['DB_HOST'] ?? 'localhost';
    $dbname = $env['DB_DATABASE'] ?? 'u815655858_saas';
    $username = $env['DB_USERNAME'] ?? 'u815655858_saas';
    $password = $env['DB_PASSWORD'] ?? 'Ekbt5WOqy#hJg#';
} else {
    $host = 'localhost';
    $dbname = 'u815655858_saas';
    $username = 'u815655858_saas';
    $password = 'Ekbt5WOqy#hJg#';
}

try {
    $db = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "👥 USUÁRIOS NO SISTEMA\n";
    echo str_repeat("=", 100) . "\n\n";
    
    $users = $db->query("
        SELECT id, name, email, role, tenant_id, created_at
        FROM users
        ORDER BY tenant_id, id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $current_tenant = null;
    foreach ($users as $user) {
        if ($current_tenant !== $user['tenant_id']) {
            $current_tenant = $user['tenant_id'];
            echo "\n🏢 TENANT {$current_tenant}\n";
            echo str_repeat("-", 100) . "\n";
        }
        
        $emoji = match(strtolower($user['role'])) {
            'super_admin', 'admin' => '👑',
            'corretor' => '👨‍💼',
            'cliente' => '👤',
            default => '❓'
        };
        
        $redirectExpected = match(strtolower($user['role'])) {
            'super_admin', 'superadmin', 'admin', 'manager', 'tenant', 'tenant_admin', 'tenantadmin' => '/app/dashboard.html',
            'corretor' => '/app/chat.html',
            default => '/portal/imoveis.html'
        };
        
        $highlight = (strpos($user['email'], 'marcus.lima') !== false) ? ' ⭐ ESTE É O USUÁRIO!' : '';
        
        echo "{$emoji} ID: {$user['id']}\n";
        echo "   Nome: {$user['name']}{$highlight}\n";
        echo "   Email: {$user['email']}\n";
        echo "   Role: {$user['role']}\n";
        echo "   Redirect esperado: {$redirectExpected}\n";
        echo "   Criado em: {$user['created_at']}\n";
        echo "\n";
    }
    
    echo str_repeat("=", 100) . "\n";
    echo "✅ Total: " . count($users) . " usuários cadastrados\n";
    
} catch (PDOException $e) {
    echo "❌ Erro ao conectar no banco: " . $e->getMessage() . "\n";
    echo "Host: {$host}\n";
    echo "Database: {$dbname}\n";
}
