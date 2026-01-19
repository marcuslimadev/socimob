<?php
/**
 * Corrigir role de 'user' para 'corretor'
 * Acesse: /fix_user_roles.php?confirm=yes
 */

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    die("⚠️  Adicione ?confirm=yes na URL para executar\n");
}

$env = file_exists(__DIR__ . '/../.env') ? parse_ini_file(__DIR__ . '/../.env') : [];
$host = $env['DB_HOST'] ?? 'localhost';
$dbname = $env['DB_DATABASE'] ?? 'u815655858_saas';
$username = $env['DB_USERNAME'] ?? 'u815655858_saas';
$password = $env['DB_PASSWORD'] ?? 'MundoMelhor@10';

try {
    $db = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "🔧 CORRIGINDO ROLES DE USUÁRIOS\n";
    echo str_repeat("=", 70) . "\n\n";
    
    // Verificar quantos users com role='user'
    $count = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    
    if ($count == 0) {
        echo "✅ Nenhum usuário com role='user' encontrado!\n";
        echo "Todos já estão corretos.\n";
        exit;
    }
    
    echo "🔍 Encontrados {$count} usuário(s) com role='user'\n\n";
    
    // Listar antes
    $users = $db->query("
        SELECT id, name, email, role 
        FROM users 
        WHERE role = 'user'
        ORDER BY id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Usuários que serão atualizados:\n";
    foreach ($users as $u) {
        echo "   - ID {$u['id']}: {$u['name']} ({$u['email']}) - role: '{$u['role']}'\n";
    }
    echo "\n";
    
    // Atualizar
    echo "🔄 Atualizando role de 'user' para 'corretor'...\n";
    $stmt = $db->prepare("UPDATE users SET role = 'corretor' WHERE role = 'user'");
    $stmt->execute();
    $updated = $stmt->rowCount();
    
    echo "✅ {$updated} usuário(s) atualizado(s)!\n\n";
    
    // Verificar depois
    $after = $db->query("
        SELECT id, name, email, role 
        FROM users 
        WHERE id IN (" . implode(',', array_column($users, 'id')) . ")
        ORDER BY id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Resultado final:\n";
    foreach ($after as $u) {
        echo "   - ID {$u['id']}: {$u['name']} ({$u['email']}) - role: '{$u['role']}' ✅\n";
    }
    
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "🎉 CORREÇÃO CONCLUÍDA!\n\n";
    echo "Agora:\n";
    echo "1. Faça logout no celular\n";
    echo "2. Limpe localStorage: localStorage.clear()\n";
    echo "3. Faça login novamente\n";
    echo "4. Deve ir direto para /app/chat.html\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
