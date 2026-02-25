<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

echo "=== ATUALIZANDO URL IMOBI BRASIL (DIRETO) ===\n\n";

$newUrl = 'https://exclusivalarimoveis.com.br/api/v1';

// Atualizar via query direto
$updated = DB::table('tenants')
    ->where('id', 1)
    ->update(['imobi_brasil_base_url' => $newUrl]);

echo "Linhas atualizadas: $updated\n\n";

// Verificar
$tenant = Tenant::find(1);
echo "URL após atualização: " . $tenant->imobi_brasil_base_url . "\n";

// Limpar cache de modelo
\Illuminate\Support\Facades\Cache::forget('tenant:1');

// Recarregar
$tenant = Tenant::find(1);
echo "URL após recarregar: " . $tenant->imobi_brasil_base_url . "\n";
