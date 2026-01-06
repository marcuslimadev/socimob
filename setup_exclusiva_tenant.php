<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

try {
    // Criar ou atualizar tenant
    $tenantData = [
        'name' => 'Exclusiva Lar Imóveis',
        'domain' => 'exclusivalarimoveis.com',
        'slug' => 'exclusiva-lar',
        'theme' => 'classico',
        'primary_color' => '#1e293b',
        'secondary_color' => '#3b82f6',
        'logo_url' => '/assets/logo-exclusiva.png',
        'description' => 'Encontre o Imóvel dos Seus Sonhos',
        'contact_email' => 'contato@exclusivalarimoveis.com.br',
        'contact_phone' => '(31) 97559-7278',
        'subscription_status' => 'active',
        'subscription_plan' => 'premium',
        'is_active' => true,
    ];
    
    $db = $app->make('db');
    
    // Verificar se já existe
    $existing = $db->table('tenants')
        ->where('domain', 'exclusivalarimoveis.com')
        ->orWhere('name', 'Exclusiva Lar Imóveis')
        ->first();
    
    if ($existing) {
        echo "🔄 Atualizando tenant existente (ID: {$existing->id})...\n";
        $db->table('tenants')
            ->where('id', $existing->id)
            ->update(array_merge($tenantData, ['updated_at' => date('Y-m-d H:i:s')]));
        $tenantId = $existing->id;
    } else {
        echo "➕ Criando novo tenant...\n";
        $tenantId = $db->table('tenants')->insertGetId(array_merge($tenantData, [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]));
    }
    
    // Verificar resultado
    $tenant = $db->table('tenants')->where('id', $tenantId)->first();
    
    echo "\n✅ Tenant configurado com sucesso!\n\n";
    echo "ID: {$tenant->id}\n";
    echo "Nome: {$tenant->name}\n";
    echo "Domínio: {$tenant->domain}\n";
    echo "Slogan: {$tenant->description}\n";
    echo "Email: {$tenant->contact_email}\n";
    echo "Telefone: {$tenant->contact_phone}\n";
    echo "Status: {$tenant->subscription_status}\n";
    echo "\n🌐 Acesse: http://{$tenant->domain} ou http://127.0.0.1:8000\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
