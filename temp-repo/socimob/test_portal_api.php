<?php

require_once __DIR__ . '/vendor/autoload.php';

// Criar aplicação Lumen
$app = require __DIR__ . '/bootstrap/app.php';

// Simular request
$request = Illuminate\Http\Request::create('/api/portal/imoveis', 'GET');

// Adicionar tenant_id ao request (simular middleware)
$request->attributes->set('tenant_id', 1);

try {
    // Obter controller
    $controller = new App\Http\Controllers\Portal\PortalController();
    
    // Executar método
    $response = $controller->getImoveis($request);
    
    echo "=== TESTE API PORTAL ===\n\n";
    
    $data = json_decode($response->getContent(), true);
    
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    echo "Total imóveis: " . ($data['total'] ?? 0) . "\n\n";
    
    if (!empty($data['data'])) {
        echo "Primeiro imóvel:\n";
        $primeiro = $data['data'][0];
        print_r([
            'id' => $primeiro['id'] ?? null,
            'codigo_imovel' => $primeiro['codigo_imovel'] ?? null,
            'tipo_imovel' => $primeiro['tipo_imovel'] ?? null,
            'finalidade_imovel' => $primeiro['finalidade_imovel'] ?? null,
            'valor_venda' => $primeiro['valor_venda'] ?? null,
            'imagens' => substr($primeiro['imagens'] ?? '', 0, 100) . '...',
            'active' => $primeiro['active'] ?? null,
            'exibir_imovel' => $primeiro['exibir_imovel'] ?? null,
        ]);
    } else {
        echo "⚠️ Nenhum imóvel retornado!\n\n";
        
        // Verificar diretamente no banco
        $db = $app->make('db');
        $count = $db->table('imo_properties')
            ->where('tenant_id', 1)
            ->where('active', true)
            ->where('exibir_imovel', true)
            ->count();
        
        echo "Imóveis no banco (tenant_id=1, active=true, exibir_imovel=true): $count\n";
        
        // Verificar configuração do tenant
        $tenant = $db->table('tenants')->where('id', 1)->first();
        if ($tenant) {
            $config = json_decode($tenant->config, true);
            echo "\nConfiguração portal_finalidades: ";
            print_r($config['portal_finalidades'] ?? null);
        }
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
