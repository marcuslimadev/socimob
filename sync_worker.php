<?php
/**
 * Worker de sincronização de imóveis
 * Executa em background para não bloquear requisições HTTP
 */

// Carregar autoload do Laravel/Lumen
require_once __DIR__ . '/vendor/autoload.php';

// Carregar aplicação
$app = require_once __DIR__ . '/bootstrap/app.php';

try {
    echo "=== INICIANDO SYNC DE IMÓVEIS ===\n";
    echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Obter service do container
    $syncService = $app->make(\App\Services\PropertySyncService::class);
    
    // Executar sync
    $result = $syncService->syncAll();
    
    if ($result['success']) {
        echo "✓ SUCESSO\n";
        echo "Total processado: {$result['total']}\n";
        echo "Novos: {$result['novos']}\n";
        echo "Atualizados: {$result['atualizados']}\n";
        echo "Erros: {$result['erros']}\n";
        
        if (!empty($result['detalhes']['erros'])) {
            echo "\nErros encontrados:\n";
            foreach ($result['detalhes']['erros'] as $erro) {
                echo "  - {$erro}\n";
            }
        }
        
        exit(0); // Sucesso
    } else {
        echo "✗ FALHA\n";
        echo "Mensagem: " . ($result['message'] ?? 'Erro desconhecido') . "\n";
        exit(1); // Falha
    }
    
} catch (\Exception $e) {
    echo "✗ EXCEÇÃO\n";
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1); // Falha
}
