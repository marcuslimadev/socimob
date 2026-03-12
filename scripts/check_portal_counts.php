<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenant = App\Models\Tenant::where('domain', 'lojadaesquina.store')->first();

if (!$tenant) {
    fwrite(STDERR, "tenant not found\n");
    exit(1);
}

$properties = App\Models\Property::withoutTenant()->where('tenant_id', $tenant->id);

$result = [
    'tenant_id' => $tenant->id,
    'all' => (clone $properties)->count(),
    'active' => (clone $properties)->where('active', 1)->count(),
    'active_exibir' => (clone $properties)->where('active', 1)->where('exibir_imovel', 1)->count(),
    'portal_rule' => (clone $properties)->where('active', 1)->where('exibir_imovel', 1)->where('valor_venda', '>=', 30000)->count(),
    'imobi_external' => (clone $properties)->whereNotNull('imobi_brasil_external_id')->count(),
    'portal_finalidades' => $tenant->config?->portal_finalidades,
    'allow_shared' => env('PORTAL_ALLOW_SHARED_PROPERTIES'),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;