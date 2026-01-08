<?php
/**
 * Verificar roles dos usuários
 */

$host = 'srv1005.hstgr.io';
$dbname = 'u815655858_saas';
$username = 'u815655858_saas';
$password = 'Ekbt5WOqy#hJg#';

try {
    $db = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "👥 USUÁRIOS E ROLES\n";
    echo str_repeat("=", 80) . "\n\n";
    
    $users = $db->query("
        SELECT id, name, email, role, tenant_id
        FROM users
        WHERE tenant_id = 1
        ORDER BY id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $roleDisplay = $user['role'];
        $emoji = match($user['role']) {
            'super_admin', 'admin' => '👑',
            'corretor' => '👨‍💼',
            'cliente' => '👤',
            default => '❓'
        };
        
        echo "{$emoji} ID: {$user['id']} | {$user['name']}\n";
        echo "   Email: {$user['email']}\n";
        echo "   Role: {$roleDisplay}\n";
        echo "   Tenant: {$user['tenant_id']}\n";
        echo "\n";
    }
    
    echo str_repeat("=", 80) . "\n";
    echo "✅ Total: " . count($users) . " usuários\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
