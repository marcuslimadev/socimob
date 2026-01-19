<?php

require_once __DIR__ . '/vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(
    __DIR__
))->bootstrap();

$app = require_once __DIR__ . '/bootstrap/app.php';

// Atualizar usuário Alexsandra
$email = 'contato@exclusivalarimoveis.com.br';
$newPassword = 'password';

// Conectar ao banco
$pdo = new PDO(
    'mysql:host=' . env('DB_HOST', '127.0.0.1') . ';dbname=' . env('DB_DATABASE', 'exclusiva'),
    env('DB_USERNAME', 'root'),
    env('DB_PASSWORD', '')
);

// Gerar hash
$hash = password_hash($newPassword, PASSWORD_BCRYPT);

// Atualizar
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
$result = $stmt->execute([$hash, $email]);

if ($result) {
    echo "✓ Senha atualizada para: $email\n";
    echo "  Nova senha: $newPassword\n";
    echo "  Hash: $hash\n";
} else {
    echo "✗ Erro ao atualizar senha\n";
}
