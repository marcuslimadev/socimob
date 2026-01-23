<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

$user = $app->make('db')->table('users')->where('email', 'marcus.lima@hotmail.com.br')->first();

if (!$user) {
    echo 'Usuario nao encontrado. Criando...' . PHP_EOL;
    
    $userId = $app->make('db')->table('users')->insertGetId([
        'name' => 'Marcus Lima',
        'email' => 'marcus.lima@hotmail.com.br',
        'password' => password_hash('password', PASSWORD_BCRYPT),
        'role' => 'admin',
        'tenant_id' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    
    echo 'Usuario criado:' . PHP_EOL;
    echo '  ID: ' . $userId . PHP_EOL;
    echo '  Email: marcus.lima@hotmail.com.br' . PHP_EOL;
    echo '  Senha: password' . PHP_EOL;
    echo '  Role: admin' . PHP_EOL;
    echo '  Tenant: 1' . PHP_EOL;
} else {
    echo 'Usuario encontrado:' . PHP_EOL;
    echo '  ID: ' . $user->id . PHP_EOL;
    echo '  Nome: ' . $user->name . PHP_EOL;
    echo '  Email: ' . $user->email . PHP_EOL;
    echo '  Role: ' . $user->role . PHP_EOL;
    echo '  Tenant: ' . $user->tenant_id . PHP_EOL;
    
    // Atualizar senha
    $app->make('db')->table('users')
        ->where('id', $user->id)
        ->update([
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    
    echo PHP_EOL . 'Senha resetada para: password' . PHP_EOL;
}
