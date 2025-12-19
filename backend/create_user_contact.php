<?php

require __DIR__ . '/vendor/autoload.php';

try {
    $app = require __DIR__ . '/bootstrap/app.php';
    
    $email = 'contato@exclusiva.com.br';
    $password = 'Teste@123';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    
    $pdo = app('db')->connection()->getPdo();
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update password
        $stmt = $pdo->prepare("UPDATE users SET password = ?, is_active = 1 WHERE email = ?");
        $stmt->execute([$hash, $email]);
        echo "✅ Usuário atualizado!\n";
    } else {
        // Create user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute(['Contato Exclusiva', $email, $hash, 'admin', 1]);
        echo "✅ Usuário criado!\n";
    }
    
    echo "Email: $email\n";
    echo "Senha: $password\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
