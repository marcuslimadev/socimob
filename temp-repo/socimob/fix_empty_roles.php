<?php
/**
 * Corrigir roles vazios para 'corretor'
 */

header('Content-Type: text/plain; charset=utf-8');

$host = 'localhost';
$db = 'u815655858_saas';
$user = 'u815655858_saas';
$pass = 'MundoMelhor@10';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔧 CORRIGINDO ROLES VAZIOS\n";
    echo str_repeat('=', 70) . "\n\n";
    
    // IDs específicos que precisam ser corretores
    $ids = [5, 7, 49]; // corretor@, Nelson, Marcus Lima
    
    foreach ($ids as $id) {
        $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "ANTES - ID {$user['id']}: {$user['name']} ({$user['email']}) - role: '{$user['role']}'\n";
            
            $update = $pdo->prepare("UPDATE users SET role = 'corretor' WHERE id = ?");
            $update->execute([$id]);
            
            $stmt->execute([$id]);
            $after = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "DEPOIS - ID {$after['id']}: {$after['name']} ({$after['email']}) - role: '{$after['role']}' ✅\n\n";
        }
    }
    
    echo str_repeat('=', 70) . "\n";
    echo "✅ CONCLUÍDO!\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
