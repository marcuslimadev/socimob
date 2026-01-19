<?php
$host = 'localhost';
$db = 'u815655858_saas';
$user = 'u815655858_saas';
$pass = 'MundoMelhor@10';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔧 ADICIONANDO 'corretor' AO ENUM\n";
    echo str_repeat('=', 70) . "\n\n";
    
    // Adicionar 'corretor' ao ENUM
    $sql = "ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','corretor','user','client') NOT NULL";
    
    echo "Executando ALTER TABLE...\n";
    $pdo->exec($sql);
    echo "✅ ENUM atualizado!\n\n";
    
    // Atualizar users vazios para corretor
    echo "Atualizando users com role vazio...\n";
    $pdo->exec("UPDATE users SET role = 'corretor' WHERE role = '' OR id IN (5, 7, 49)");
    echo "✅ Users atualizados!\n\n";
    
    // Verificar
    echo "📋 VERIFICANDO:\n";
    $stmt = $pdo->query("SELECT id, name, email, role FROM users WHERE id IN (5, 7, 49)");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $u) {
        echo "   - ID {$u['id']}: {$u['name']} ({$u['email']}) - role: '{$u['role']}'\n";
    }
    
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "✅ CONCLUÍDO!\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
