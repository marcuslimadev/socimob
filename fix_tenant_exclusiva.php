<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

// Pegar primeiro tenant (ou ID 1)
$tenant = $app->make('db')->table('tenants')->first();
if (!$tenant) {
    echo "ERRO: Nenhum tenant encontrado!" . PHP_EOL;
    exit(1);
}

$tenantId = $tenant->id;
echo "Tenant ID: " . $tenantId . PHP_EOL;
echo "Tenant nome: " . $tenant->name . PHP_EOL;

// Atualizar usuário marcus para ter o tenant correto
$updated = $app->make('db')->table('users')
    ->where('email', 'marcus.lima@hotmail.com.br')
    ->update([
        'tenant_id' => $tenantId,
        'role' => 'admin',
        'password' => password_hash('password', PASSWORD_BCRYPT),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

if ($updated) {
    echo PHP_EOL . "✓ Usuario marcus.lima@hotmail.com.br atualizado!" . PHP_EOL;
    echo "✓ Tenant ID: " . $tenantId . PHP_EOL;
    echo "✓ Senha: password" . PHP_EOL;
    echo "✓ Role: admin" . PHP_EOL;
    
    // Verificar se login funciona
    $user = $app->make('db')->table('users')->where('email', 'marcus.lima@hotmail.com.br')->first();
    if ($user) {
        echo PHP_EOL . "Usuário confirmado no banco:";
        echo PHP_EOL . "  ID: " . $user->id;
        echo PHP_EOL . "  Nome: " . $user->name;
        echo PHP_EOL . "  Email: " . $user->email;
        echo PHP_EOL . "  Tenant: " . $user->tenant_id;
        echo PHP_EOL . "  Role: " . $user->role;
    }
} else {
    echo "ERRO ao atualizar usuario!" . PHP_EOL;
}
