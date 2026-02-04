<?php
/**
 * Script de sincronização direta
 * Acesse: https://exclusivalarimoveis.com/sync_now.php
 */

// Definir tempo limite maior
set_time_limit(300); // 5 minutos
ini_set('max_execution_time', '300');

// Iniciar output buffering para mostrar progresso
ob_implicit_flush(true);
ob_end_flush();

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Sincronização de Imóveis</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .progress { color: #666; font-family: monospace; white-space: pre-wrap; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔄 Sincronização de Imóveis</h1>
";

try {
    // Carregar Laravel
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $app->boot();
    
    echo "<div class='status info'>✅ Sistema carregado com sucesso</div>";
    flush();
    
    // Simular tenant (Exclusiva Lar - ID 1)
    $tenant = \App\Models\Tenant::find(1);
    
    if (!$tenant) {
        throw new Exception("Tenant não encontrado");
    }
    
    app()->instance('tenant', $tenant);
    echo "<div class='status info'>🏢 Tenant: {$tenant->nome} (ID: {$tenant->id})</div>";
    flush();
    
    // Executar sincronização
    echo "<div class='status info'>🚀 Iniciando sincronização...</div>";
    echo "<div class='progress'>";
    flush();
    
    $syncService = app(\App\Services\PropertySyncService::class);
    $result = $syncService->syncAll();
    
    echo "</div>";
    
    echo "<div class='status success'>";
    echo "<h3>✅ Sincronização Concluída!</h3>";
    echo "<p><strong>Imóveis encontrados:</strong> {$result['found']}</p>";
    echo "<p><strong>Novos imóveis:</strong> {$result['new']}</p>";
    echo "<p><strong>Atualizados:</strong> {$result['updated']}</p>";
    echo "<p><strong>Erros:</strong> {$result['errors']}</p>";
    echo "<p><strong>Tempo:</strong> {$result['elapsed']}ms</p>";
    echo "</div>";
    
    if (!empty($result['errorDetails'])) {
        echo "<div class='status error'>";
        echo "<h4>❌ Detalhes dos Erros:</h4>";
        echo "<pre>" . json_encode($result['errorDetails'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        echo "</div>";
    }
    
    // Verificar imagens atualizadas
    $comImagens = \App\Models\Property::whereNotNull('imagens')
        ->where('imagens', '!=', '[]')
        ->where('imagens', '!=', '')
        ->count();
    
    echo "<div class='status info'>";
    echo "<p>📸 <strong>Imóveis com imagens:</strong> {$comImagens}</p>";
    echo "</div>";
    
} catch (\Exception $e) {
    echo "<div class='status error'>";
    echo "<h3>❌ Erro:</h3>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "
    <div class='status info' style='margin-top: 20px;'>
        <p>🔄 Para executar nova sincronização, <a href=''>recarregue esta página</a></p>
    </div>
</div>
</body>
</html>";
