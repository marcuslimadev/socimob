<?php

// Conectar diretamente ao MySQL
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=exclusiva', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CONSULTANDO TENANT ID 1 ===\n\n";
    
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = 1");
    $stmt->execute();
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        echo "Tenant ID 1 não encontrado!\n";
        
        // Vamos ver todos os tenants
        echo "\n=== TODOS OS TENANTS ===\n";
        $stmt = $pdo->prepare("SELECT id, name, domain, subdomain FROM tenants");
        $stmt->execute();
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tenants as $t) {
            echo "ID: {$t['id']} | Nome: {$t['name']} | Domain: {$t['domain']} | Subdomain: {$t['subdomain']}\n";
        }
        exit;
    }
    
    echo "ID: " . $tenant['id'] . "\n";
    echo "Nome: " . $tenant['name'] . "\n";
    echo "Domain: " . $tenant['domain'] . "\n";
    echo "Subdomain: " . $tenant['subdomain'] . "\n";
    echo "\n=== CONFIGURAÇÃO API EXTERNA ===\n";
    echo "URL API Externa: " . ($tenant['api_url_externa'] ?? 'NÃO CONFIGURADO') . "\n";
    echo "Token API Externa: " . ($tenant['api_token_externa'] ?? 'NÃO CONFIGURADO') . "\n";
    echo "\n=== CONFIGURAÇÕES PORTAL ===\n";
    echo "Portal Slogan: " . ($tenant['portal_slogan'] ?? 'NÃO CONFIGURADO') . "\n";
    echo "Portal Theme: " . ($tenant['portal_theme'] ?? 'NÃO CONFIGURADO') . "\n";
    
} catch (Exception $e) {
    echo "Erro de conexão: " . $e->getMessage() . "\n";
}