<?php
/**
 * SCRIPT PARA CONFIGURAR A URL CORRETA DA API IMOBI BRASIL
 * 
 * Execute este script após descobrir a URL correta:
 * php configure_imobi_url.php "https://seu-endpoint-correto.com"
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

if (php_sapi_name() !== 'cli') {
    echo "Este script deve ser executado via CLI\n";
    exit(1);
}

$newUrl = $argv[1] ?? null;

if (!$newUrl) {
    echo "=== CONFIGURADOR DE URL IMOBI BRASIL ===\n\n";
    echo "Uso: php configure_imobi_url.php \"https://sua-url-correta.com\"\n\n";
    echo "Exemplos:\n";
    echo "  php configure_imobi_url.php \"https://imobibrasil.com.br/api\"\n";
    echo "  php configure_imobi_url.php \"https://app.imobibrasil.com.br\"\n";
    echo "  php configure_imobi_url.php \"https://api.seuservidor.com.br\"\n\n";
    
    echo "--- Configuração Atual ---\n";
    $tenant = Tenant::find(1);
    if ($tenant) {
        echo "Tenant: " . $tenant->name . "\n";
        echo "URL Base: " . $tenant->imobi_brasil_base_url . "\n";
    }
    exit(0);
}

if (!filter_var($newUrl, FILTER_VALIDATE_URL)) {
    echo "❌ URL inválida: $newUrl\n";
    exit(1);
}

$tenant = Tenant::find(1);
if (!$tenant) {
    echo "❌ Tenant não encontrado!\n";
    exit(1);
}

echo "=== ATUALIZANDO URL IMOBI BRASIL ===\n\n";
echo "De: " . $tenant->imobi_brasil_base_url . "\n";
echo "Para: " . $newUrl . "\n\n";

$tenant->update([
    'imobi_brasil_base_url' => $newUrl,
]);

echo "✅ URL atualizada com sucesso!\n";
echo "   Você pode agora tenstar a integração.\n";
