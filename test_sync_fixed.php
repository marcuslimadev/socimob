<?php

require_once 'bootstrap/app.php';

use App\Services\PropertySyncService;

try {
    echo "🏠 Testando sincronização com mapeamento corrigido...\n\n";
    
    $syncService = new PropertySyncService();
    
    echo "📡 Iniciando sync...\n";
    $result = $syncService->syncAll();
    
    if ($result['success']) {
        echo "\n✅ SUCESSO!\n\n";
        echo "Estatísticas:\n";
        foreach ($result['stats'] as $key => $value) {
            echo "  - " . ucfirst($key) . ": $value\n";
        }
        echo "\nTempo: " . $result['time_ms'] . " ms\n";
    } else {
        echo "\n❌ ERRO: " . $result['error'] . "\n";
    }
    
    // Mostrar últimos 3 imóveis importados
    echo "\n📋 Últimos 3 imóveis no banco:\n";
    $properties = app('db')->table('imo_properties')
        ->orderBy('id', 'desc')
        ->limit(3)
        ->get();
    
    foreach ($properties as $prop) {
        echo "\n  ID: {$prop->id}\n";
        echo "  Código: {$prop->codigo}\n";
        echo "  Título: {$prop->titulo}\n";
        echo "  Tipo: {$prop->tipo_imovel}\n";
        echo "  Finalidade: {$prop->finalidade_imovel}\n";
        echo "  Preço: R$ " . number_format($prop->preco, 2, ',', '.') . "\n";
        echo "  Endereço: {$prop->endereco}\n";
        echo "  Cidade: {$prop->cidade} - {$prop->estado}\n";
        echo "  Quartos: {$prop->quartos} | Banheiros: {$prop->banheiros} | Vagas: {$prop->vagas}\n";
        echo "  ---\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
