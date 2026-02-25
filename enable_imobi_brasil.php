<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tenant;

$tenant = Tenant::find(1);

if (!$tenant) {
    echo "❌ Tenant não encontrado!\n";
    exit(1);
}

echo "=== HABILITANDO INTEGRAÇÃO IMOBI BRASIL ===\n\n";

$tenant->update([
    'imobi_brasil_enabled' => true,
]);

echo "✅ Integração Imobi Brasil habilitada para o tenant!\n";
echo "   - imobi_brasil_enabled: true\n";
echo "   - Chave de API: Lida de EXCLUSIVA_API_TOKEN\n";
