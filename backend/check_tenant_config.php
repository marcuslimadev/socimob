<?php

require_once __DIR__.'/vendor/autoload.php';

// Configurar o Lumen
$app = require_once __DIR__.'/bootstrap/app.php';

try {
    // Buscar tenant ID 1
    $tenant = App\Models\Tenant::find(1);
    
    if (!$tenant) {
        echo "Tenant ID 1 não encontrado!\n";
        exit;
    }
    
    echo "=== DADOS DO TENANT ===\n";
    echo "ID: " . $tenant->id . "\n";
    echo "Nome: " . $tenant->name . "\n";
    echo "Domain: " . $tenant->domain . "\n";
    echo "Subdomain: " . $tenant->subdomain . "\n";
    echo "\n=== CONFIGURAÇÃO API EXTERNA ===\n";
    echo "URL API Externa: " . ($tenant->api_url_externa ?? 'NÃO CONFIGURADO') . "\n";
    echo "Token API Externa: " . ($tenant->api_token_externa ?? 'NÃO CONFIGURADO') . "\n";
    echo "\n=== CONFIGURAÇÕES PORTAL ===\n";
    echo "Portal Slogan: " . ($tenant->portal_slogan ?? 'NÃO CONFIGURADO') . "\n";
    echo "Portal Theme: " . ($tenant->portal_theme ?? 'NÃO CONFIGURADO') . "\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}