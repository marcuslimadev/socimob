<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

try {
    $tenant = $app->make('db')->table('tenants')->first();
    
    if ($tenant) {
        echo "✅ Tenant encontrado:\n";
        echo json_encode($tenant, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo "❌ Nenhum tenant encontrado no banco\n";
    }
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
