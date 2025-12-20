<?php

require __DIR__ . '/vendor/autoload.php';

try {
    $app = require __DIR__ . '/bootstrap/app.php';
    
    $email = 'contato@exclusiva.com.br';
    $password = 'Teste@123';
    
    // Test login directly
    $user = \App\Models\User::where('email', $email)
        ->where('is_active', 1)
        ->first();
    
    if (!$user) {
        echo "❌ Usuário não encontrado\n";
        exit;
    }
    
    echo "✅ Usuário encontrado: " . $user->name . "\n";
    
    if (!password_verify($password, $user->password)) {
        echo "❌ Senha incorreta\n";
        exit;
    }
    
    echo "✅ Senha correta\n";
    
    // Test token generation
    $secret = env('JWT_SECRET', env('APP_KEY', 'default-secret-key'));
    $token = base64_encode($user->id . '|' . time() . '|' . $secret);
    
    echo "✅ Token gerado: " . substr($token, 0, 50) . "...\n";
    
    // Test response format
    $response = [
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]
    ];
    
    echo "✅ Resposta (JSON):\n";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
