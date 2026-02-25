<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Verificar via query direto
$result = DB::table('tenants')
    ->select('id', 'name', 'imobi_brasil_enabled', 'imobi_brasil_api_key')
    ->where('id', 1)
    ->first();

echo "=== VERIFICAÇÃO DIRETA DO BANCO (Query) ===\n\n";
if ($result) {
    echo "ID: " . $result->id . "\n";
    echo "Name: " . $result->name . "\n";
    echo "imobi_brasil_enabled: " . ($result->imobi_brasil_enabled ? 'true' : 'false') . "\n";
    echo "imobi_brasil_api_key: " . ($result->imobi_brasil_api_key ? 'Preenchida' : 'Vazia') . "\n";
} else {
    echo "❌ Tenant não encontrado!\n";
}

// Tentar update direto
echo "\n--- Tentando UPDATE direto ---\n";
$updated = DB::table('tenants')
    ->where('id', 1)
    ->update([
        'imobi_brasil_enabled' => 1,
    ]);

echo "Linhas atualizadas: $updated\n";

// Verificar novamente
echo "\n--- Verificando após UPDATE ---\n";
$result = DB::table('tenants')
    ->select('id', 'name', 'imobi_brasil_enabled')
    ->where('id', 1)
    ->first();

if ($result) {
    echo "imobi_brasil_enabled: " . ($result->imobi_brasil_enabled ? 'true' : 'false') . "\n";
}
