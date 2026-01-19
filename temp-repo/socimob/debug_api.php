<?php
// Verificar dados no banco
$pdo = new PDO('mysql:host=localhost;dbname=exclusiva', 'root', '');

// Listar todos os usuários
$stmt = $pdo->query("SELECT id, name, email, role, is_active FROM users ORDER BY id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== USUÁRIOS NO BANCO ===\n";
if (empty($users)) {
    echo "❌ Nenhum usuário encontrado\n";
} else {
    echo "✅ Total de usuários: " . count($users) . "\n";
    foreach ($users as $user) {
        echo "  [{$user['id']}] {$user['name']} ({$user['email']}) - Role: {$user['role']} - Ativo: {$user['is_active']}\n";
    }
}

// Testar token
echo "\n=== TESTE DE TOKEN ===\n";
$secret = 'base64:Zm9vYmFy'; // APP_KEY padrão do .env
$userId = 1; // Admin user
$token = base64_encode($userId . '|' . time() . '|' . $secret);
echo "Token de teste: " . substr($token, 0, 50) . "...\n";

// Simular request da API
echo "\n=== TESTANDO MIDDLEWARE ===\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

$authHeader = 'Bearer ' . $token;
if (!$authHeader || stripos($authHeader, 'bearer ') !== 0) {
    echo "❌ Header inválido\n";
} else {
    $tokenFromHeader = trim(substr($authHeader, 7));
    $decoded = base64_decode($tokenFromHeader, true);
    
    if (!$decoded || !str_contains($decoded, '|')) {
        echo "❌ Token inválido\n";
    } else {
        [$extractedUserId] = explode('|', $decoded, 3);
        echo "✅ Token decodificado com sucesso\n";
        echo "  User ID extraído: $extractedUserId\n";
        
        // Buscar usuário
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$extractedUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "✅ Usuário encontrado: {$user['name']}\n";
            echo "  Role: {$user['role']}\n";
            echo "  é Super Admin? " . ($user['role'] === 'super_admin' ? 'SIM' : 'NÃO') . "\n";
        } else {
            echo "❌ Usuário não encontrado\n";
        }
    }
}
