<?php
/**
 * Debug endpoint - verificar role do usuário logado
 * Acesse: /debug_role.php?email=corretor@exclusiva.com
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$email = $_GET['email'] ?? null;

if (!$email) {
    echo json_encode(['error' => 'Forneça o email via ?email=xxx@xxx.com']);
    exit;
}

// Conectar direto ao banco
$host = getenv('DB_HOST') ?: 'srv1005.hstgr.io';
$dbname = getenv('DB_DATABASE') ?: 'u815655858_saas';
$username = getenv('DB_USERNAME') ?: 'u815655858_saas';
$password = getenv('DB_PASSWORD') ?: 'Ekbt5WOqy#hJg#';

try {
    $db = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password
    );
    
    $stmt = $db->prepare("SELECT id, name, email, role, tenant_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'Usuário não encontrado', 'email_buscado' => $email]);
        exit;
    }
    
    $role = strtolower($user['role']);
    $adminRoles = ['super_admin', 'superadmin', 'admin', 'manager', 'tenant', 'tenant_admin', 'tenantadmin'];
    
    if (in_array($role, $adminRoles)) {
        $redirect = '/app/dashboard.html';
    } elseif ($role === 'corretor') {
        $redirect = '/app/chat.html';
    } else {
        $redirect = '/portal/imoveis.html';
    }
    
    echo json_encode([
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'role_normalizado' => $role,
        'redirect_esperado' => $redirect,
        'tenant_id' => $user['tenant_id'],
        'url_completa' => 'https://lojadaesquina.store' . $redirect
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro DB: ' . $e->getMessage()]);
}

