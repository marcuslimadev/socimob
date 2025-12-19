<?php
$pdo = new PDO('mysql:host=localhost;dbname=exclusiva', 'root', '');
$stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
$password = password_hash('Senha@123', PASSWORD_BCRYPT);
$stmt->execute(['Alexsandra Silva', 'alexsandra@exclusiva.com.br', $password, 'admin', 1]);
echo "Usuário criado!";
