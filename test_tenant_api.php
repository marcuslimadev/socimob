<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

try {
    echo "🔍 Verificando tenants no banco...\n\n";
    
    $tenants = $app->make('db')->table('tenants')
        ->where('is_active', 1)
        ->get();
    
    if ($tenants->isEmpty()) {
        echo "❌ Nenhum tenant ativo encontrado\n";
        exit(1);
    }
    
    echo "✅ Tenants ativos encontrados: " . count($tenants) . "\n\n";
    
    foreach ($tenants as $tenant) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "ID: {$tenant->id}\n";
        echo "Nome: {$tenant->name}\n";
        echo "Domínio: {$tenant->domain}\n";
        echo "Slug: {$tenant->slug}\n";
        echo "Logo: {$tenant->logo_url}\n";
        echo "Email: {$tenant->contact_email}\n";
        echo "Telefone: {$tenant->contact_phone}\n";
        echo "Descrição: {$tenant->description}\n";
        echo "Status: {$tenant->subscription_status}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }
    
    echo "🌐 A homepage vai carregar as infos do primeiro tenant ativo\n";
    echo "📍 Acesse: http://127.0.0.1:8000 para ver\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
