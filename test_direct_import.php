<?php

require_once __DIR__.'/vendor/autoload.php';

// Configurar o Lumen
$app = require_once __DIR__.'/bootstrap/app.php';

try {
    echo "=== TESTE DE IMPORTAÇÃO DIRETO ===\n\n";
    
    // Buscar usuário
    $user = App\Models\User::where('email', 'contato@exclusivalarimoveis.com.br')->first();
    
    if (!$user) {
        echo "❌ Usuário não encontrado\n";
        exit;
    }
    
    echo "👤 Usuário: {$user->name} (ID: {$user->id}, Tenant: {$user->tenant_id})\n";
    
    // Buscar tenant
    $tenant = App\Models\Tenant::find($user->tenant_id);
    
    if (!$tenant) {
        echo "❌ Tenant não encontrado\n";
        exit;
    }
    
    echo "🏢 Tenant: {$tenant->name}\n";
    echo "🔗 API URL: {$tenant->api_url_externa}\n";
    echo "🔑 Token API: " . substr($tenant->api_token_externa, 0, 20) . "...\n\n";
    
    // Chamar o controller diretamente
    $controller = new App\Http\Controllers\Admin\ImportacaoController();
    
    // Criar uma request falsa
    $request = new \Illuminate\Http\Request();
    $request->merge(['fonte' => 'exclusiva']);
    
    // Simular usuário autenticado
    $request->setUserResolver(function () use ($user) {
        return $user;
    });
    
    echo "🚀 Iniciando importação...\n\n";
    
    // Executar importação
    $response = $controller->importar($request);
    
    // Verificar resposta
    $statusCode = $response->getStatusCode();
    $content = $response->getContent();
    
    echo "Status: $statusCode\n";
    echo "Resposta:\n$content\n\n";
    
    if ($statusCode === 200) {
        $data = json_decode($content, true);
        
        if (isset($data['success']) && $data['success']) {
            echo "🎉 IMPORTAÇÃO BEM-SUCEDIDA!\n";
            
            // Verificar quantos imóveis foram importados
            $total = App\Models\Property::where('tenant_id', $user->tenant_id)->count();
            echo "Total de imóveis no banco: $total\n";
            
            if ($total > 0) {
                // Mostrar alguns exemplos
                $imoveis = App\Models\Property::where('tenant_id', $user->tenant_id)
                    ->orderBy('id', 'desc')
                    ->limit(3)
                    ->get(['title', 'price', 'property_type', 'created_at']);
                
                echo "\nÚltimos imóveis importados:\n";
                foreach ($imoveis as $imovel) {
                    echo "- {$imovel->title} | {$imovel->property_type} | R$ {$imovel->price}\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}