<?php

/**
 * Insere Ad Account da Exclusiva + entitlement básico para Meta Ads
 * Execução: php setup_ads_exclusiva.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CONFIGURAR ADS AUTOMATION — EXCLUSIVA ===\n\n";

$tenantId = 1; // Exclusiva Lar Imoveis
$provider = 'meta';
$externalAccountId = 'act_137441020060069';
$now = date('Y-m-d H:i:s');

// --------------------------------------------------------------------------
// 1. Verificar tenant
// --------------------------------------------------------------------------
$tenant = DB::table('tenants')->find($tenantId);
if (!$tenant) {
    echo "❌ Tenant ID {$tenantId} não encontrado!\n";
    exit(1);
}
$tenantName = $tenant->name ?? $tenant->nome ?? "Tenant {$tenantId}";
echo "✅ Tenant: {$tenantName} (ID: {$tenantId})\n\n";

// --------------------------------------------------------------------------
// 2. ads_accounts — conta de anúncios Meta
// --------------------------------------------------------------------------
$existing = DB::table('ads_accounts')
    ->where('tenant_id', $tenantId)
    ->where('provider', $provider)
    ->where('external_account_id', $externalAccountId)
    ->first();

if ($existing) {
    DB::table('ads_accounts')
        ->where('id', $existing->id)
        ->update([
            'name'       => 'Exclusiva Lar Imoveis — Meta Ads',
            'currency'   => 'BRL',
            'timezone'   => 'America/Sao_Paulo',
            'is_active'  => 1,
            'updated_at' => $now,
        ]);
    echo "🔄 ads_accounts atualizado (ID: {$existing->id})\n";
} else {
    $accountId = DB::table('ads_accounts')->insertGetId([
        'tenant_id'           => $tenantId,
        'provider'            => $provider,
        'external_account_id' => $externalAccountId,
        'name'                => 'Exclusiva Lar Imoveis — Meta Ads',
        'currency'            => 'BRL',
        'timezone'            => 'America/Sao_Paulo',
        'is_active'           => 1,
        'metadata_json'       => json_encode(['source' => 'manual_setup']),
        'created_at'          => $now,
        'updated_at'          => $now,
    ]);
    echo "✅ ads_accounts criado (ID: {$accountId})\n";
}

// --------------------------------------------------------------------------
// 3. ads_entitlements — plano básico para habilitar o módulo
// --------------------------------------------------------------------------
$entitlement = DB::table('ads_entitlements')
    ->where('tenant_id', $tenantId)
    ->where('plan_code', 'ADS_BASIC')
    ->first();

if ($entitlement) {
    DB::table('ads_entitlements')
        ->where('id', $entitlement->id)
        ->update([
            'providers_allowed'       => json_encode(['meta']),
            'max_listings_per_day'    => 50,
            'max_budget_daily_cents'  => 50000, // R$ 500
            'is_active'               => 1,
            'valid_from'              => $now,
            'valid_until'             => null,
            'updated_at'              => $now,
        ]);
    echo "🔄 ads_entitlements atualizado (ID: {$entitlement->id})\n";
} else {
    $entitlementId = DB::table('ads_entitlements')->insertGetId([
        'tenant_id'               => $tenantId,
        'plan_code'               => 'ADS_BASIC',
        'providers_allowed'       => json_encode(['meta']),
        'max_listings_per_day'    => 50,
        'max_budget_daily_cents'  => 50000, // R$ 500
        'remarketing_enabled'     => 0,
        'capi_enabled'            => 0,
        'multi_account_enabled'   => 0,
        'is_active'               => 1,
        'valid_from'              => $now,
        'valid_until'             => null,
        'created_at'              => $now,
        'updated_at'              => $now,
    ]);
    echo "✅ ads_entitlements criado (ID: {$entitlementId})\n";
}

// --------------------------------------------------------------------------
// 4. Verificação final
// --------------------------------------------------------------------------
echo "\n─────────────────────────────────────────\n";
echo "📋 Estado atual:\n\n";

$account = DB::table('ads_accounts')
    ->where('tenant_id', $tenantId)
    ->where('provider', $provider)
    ->first();

echo "  Ad Account  : {$account->external_account_id}\n";
echo "  Nome        : {$account->name}\n";
echo "  Moeda       : {$account->currency}\n";
echo "  Ativo       : " . ($account->is_active ? 'Sim' : 'Não') . "\n\n";

$ent = DB::table('ads_entitlements')
    ->where('tenant_id', $tenantId)
    ->where('is_active', 1)
    ->first();

echo "  Plano       : {$ent->plan_code}\n";
echo "  Providers   : {$ent->providers_allowed}\n";
echo "  Budget máx  : R$ " . number_format($ent->max_budget_daily_cents / 100, 2, ',', '.') . "/dia\n\n";

$conn = DB::table('ads_connections')
    ->where('tenant_id', $tenantId)
    ->where('provider', $provider)
    ->first();

if ($conn) {
    echo "  Conexão OAuth: {$conn->status}\n";
    echo "  Expira em    : {$conn->expires_at}\n";
} else {
    echo "  Conexão OAuth: ⚠️  Ainda não conectado — faça o OAuth no sistema\n";
}

echo "\n─────────────────────────────────────────\n";
echo "✅ Configuração concluída!\n";
echo "\n⚡ Próximo passo: acesse /ads → Conexões → 'Conectar via OAuth' (Meta)\n";
