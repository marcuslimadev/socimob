<?php

require __DIR__ . '/vendor/autoload.php';

try {
    $app = require __DIR__ . '/bootstrap/app.php';
    
    // Check database connection
    $pdo = app('db')->connection()->getPdo();
    echo "✅ Conexão com banco de dados OK\n\n";
    
    // Check users table exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "📊 Total de usuários na tabela: " . $result['count'] . "\n\n";
    
    // List all users
    $stmt = $pdo->prepare("SELECT id, name, email, role, is_active FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$users) {
        echo "❌ Nenhum usuário encontrado!\n";
    } else {
        echo "📋 Usuários encontrados:\n";
        foreach ($users as $user) {
            echo "  - ID: {$user['id']}, Nome: {$user['name']}, Email: {$user['email']}, Role: {$user['role']}, Ativo: {$user['is_active']}\n";
        }
    }
    
    // Check super admin users
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'super_admin'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n👑 Super admins: " . $result['count'] . "\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
