<?php

$host = 'srv1005.hstgr.io';
$db = 'u815655858_saas';
$user = 'u815655858_saas';
$pass = 'MundoMelhor@10';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_TIMEOUT => 30,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $stmt = $pdo->query("DESCRIBE conversas");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colunas da tabela 'conversas':\n\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']}) " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
