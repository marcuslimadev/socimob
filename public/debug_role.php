<?php
/**
 * Debug endpoint - verificar role do usuário logado
 * Acesse: /debug_role.php?email=corretor@exclusiva.com
 */

require __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $app = require __DIR__ . '/bootstrap/app.php';
    
    $email = $_GET['email'] ?? null;
    
    if (!$email) {
        echo json_encode(['error' => 'Forneça o email via ?email=']);
        exit;
    }
    
    $user = \App\Models\User::where('email', $email)->first();
    
    if (!$user) {
        echo json_encode(['error' => 'Usuário não encontrado']);
        exit;
    }
    
    $redirectUrl = match(strtolower($user->role)) {
        'super_admin', 'superadmin', 'admin', 'manager', 'tenant', 'tenant_admin', 'tenantadmin' => '/app/dashboard.html',
        'corretor' => '/app/chat.html',
        default => '/portal/imoveis.html'
    };
    
    echo json_encode([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role,
        'role_lower' => strtolower($user->role),
        'redirect_esperado' => $redirectUrl,
        'tenant_id' => $user->tenant_id
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
