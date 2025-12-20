<?php

$hash = password_hash('password', PASSWORD_BCRYPT);

$pdo = new PDO('mysql:host=localhost;dbname=exclusiva', 'root', '');
$stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
$stmt->execute(['Super Admin', 'admin@exclusiva.com', $hash, 'super_admin', 1]);

echo "✅ Super admin criado!\n";
echo "Email: admin@exclusiva.com\n";
echo "Senha: password\n";
