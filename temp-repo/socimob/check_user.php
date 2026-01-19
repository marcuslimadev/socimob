<?php

require __DIR__ . '/vendor/autoload.php';

try {
    $app = require __DIR__ . '/bootstrap/app.php';
    
    $email = 'contato@exclusiva.com.br';
    $password = 'Teste@123';
    
    $pdo = app('db')->connection()->getPdo();
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✅ Usuário encontrado: " . $user['name'] . " (ID: " . $user['id'] . ")\n";
        echo "Status (is_active): " . $user['is_active'] . "\n";
        
        if (password_verify($password, $user['password'])) {
            echo "✅ Senha CORRETA!\n";
        } else {
            echo "❌ Senha INCORRETA!\n";
            echo "Atualizando senha para '$password'...\n";
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $user['id']]);
            echo "✅ Senha atualizada com sucesso!\n";
        }
    } else {
        echo "❌ Usuário NÃO encontrado!\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
