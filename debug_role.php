<?php
$host = 'localhost';
$db = 'u815655858_saas';
$user = 'u815655858_saas';
$pass = 'MundoMelhor@10';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 ESTRUTURA DA TABELA USERS\n";
    echo str_repeat('=', 70) . "\n\n";
    
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo sprintf("%-20s %-15s %s\n", $col['Field'], $col['Type'], $col['Null']);
    }
    
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "🧪 TESTE DE UPDATE DIRETO\n\n";
    
    // Testar update direto
    $pdo->exec("UPDATE users SET role = 'corretor' WHERE id = 49");
    
    $stmt = $pdo->prepare("SELECT id, name, email, role, LENGTH(role) as len FROM users WHERE id = 49");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "ID: {$user['id']}\n";
    echo "Nome: {$user['name']}\n";
    echo "Email: {$user['email']}\n";
    echo "Role: '{$user['role']}'\n";
    echo "Length: {$user['len']}\n";
    echo "HEX: " . bin2hex($user['role']) . "\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
