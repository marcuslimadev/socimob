<?php
// Script rápido para verificar roles dos usuários
$host = 'localhost';
$db = 'u815655858_saas';
$user = 'u815655858_saas';
$pass = 'MundoMelhor@10';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "📋 ROLES ATUAIS DOS USUÁRIOS\n";
    echo str_repeat('=', 70) . "\n\n";
    
    $stmt = $pdo->query("SELECT id, name, email, role FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $roleIcon = match($user['role']) {
            'super_admin' => '👑',
            'admin' => '🔧',
            'corretor' => '💼',
            'cliente' => '👤',
            default => '❓'
        };
        
        printf("   %s ID %d: %s (%s) - role: '%s'\n", 
            $roleIcon,
            $user['id'], 
            $user['name'], 
            $user['email'], 
            $user['role']
        );
    }
    
    echo "\n" . str_repeat('=', 70) . "\n";
    
    // Contadores por role
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n📊 RESUMO:\n";
    foreach ($counts as $c) {
        echo "   - {$c['role']}: {$c['count']} usuário(s)\n";
    }
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
