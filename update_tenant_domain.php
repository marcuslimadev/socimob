<?php

// Script simples para atualizar domínio do tenant (compatível com PHP 8.0)
$dsn = 'mysql:host=localhost;dbname=u815655858_saas';
$username = 'u815655858_saas';
$password = 'MundoMelhor@10';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar tenant atual
    $stmt = $pdo->query("SELECT id, name, domain FROM tenants WHERE id = 1");
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        echo "Tenant ID 1 não encontrado\n";
        exit(1);
    }
    
    echo "Domínio atual: " . $tenant['domain'] . "\n";
    
    // Atualizar domínio
    $stmt = $pdo->prepare("UPDATE tenants SET domain = ? WHERE id = 1");
    $stmt->execute(['exclusivalarimoveis.com']);
    
    echo "Domínio atualizado para: exclusivalarimoveis.com\n";
    echo "Sucesso!\n";
    
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}

