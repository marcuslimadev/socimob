<?php
/**
 * Atualizar role do Marcus para corretor
 * Acesse: /update_marcus_role.php?confirm=yes
 */

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    die("⚠️  Adicione ?confirm=yes na URL para executar\n");
}

// Ler .env
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
    
    $email = 'marcus.lima@hotmail.com.br';
    
    echo "🔍 Procurando usuário: {$email}\n";
    echo str_repeat("=", 60) . "\n\n";
    
    // Verificar se existe
    $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ Usuário NÃO encontrado no banco!\n";
        echo "Email buscado: {$email}\n\n";
        echo "Usuários existentes:\n";
        
        $all = $db->query("SELECT email FROM users ORDER BY id LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($all as $e) {
            echo "  - {$e}\n";
        }
        exit;
    }
    
    echo "✅ Usuário encontrado:\n";
    echo "   ID: {$user['id']}\n";
    echo "   Nome: {$user['name']}\n";
    echo "   Email: {$user['email']}\n";
    echo "   Role ATUAL: {$user['role']}\n";
    echo "\n";
    
    if (strtolower($user['role']) === 'corretor') {
        echo "✅ Role já é 'corretor' - NADA A FAZER!\n";
        echo "\n";
        echo "O problema deve ser cache do navegador.\n";
        echo "Limpe o localStorage no celular:\n";
        echo "  localStorage.clear()\n";
        exit;
    }
    
    // Atualizar para corretor
    echo "🔄 Atualizando role para 'corretor'...\n";
    
    $stmt = $db->prepare("UPDATE users SET role = 'corretor' WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    echo "✅ Role atualizada com sucesso!\n";
    echo "\n";
    
    // Verificar
    $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "📋 Dados atualizados:\n";
    echo "   ID: {$updated['id']}\n";
    echo "   Nome: {$updated['name']}\n";
    echo "   Email: {$updated['email']}\n";
    echo "   Role NOVA: {$updated['role']}\n";
    echo "\n";
    echo str_repeat("=", 60) . "\n";
    echo "✅ CONCLUÍDO!\n\n";
    echo "Agora:\n";
    echo "1. Limpe o localStorage no celular: localStorage.clear()\n";
    echo "2. Faça logout completo\n";
    echo "3. Faça login novamente\n";
    echo "4. Deve ir direto para /app/chat.html\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
