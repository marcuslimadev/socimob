<?php

/**
 * Configurar número de WhatsApp do tenant
 * Execução: php configure_whatsapp_number.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CONFIGURAR NÚMERO DE WHATSAPP DO TENANT ===\n\n";

// Listar tenants
$tenants = DB::table('tenants')->get();

echo "Tenants disponíveis:\n";
foreach ($tenants as $tenant) {
    echo "  [{$tenant->id}] {$tenant->nome}\n";
}

echo "\nDigite o ID do tenant: ";
$tenantId = trim(fgets(STDIN));

$tenant = DB::table('tenants')->find($tenantId);
if (!$tenant) {
    echo "❌ Tenant não encontrado!\n";
    exit(1);
}

echo "\n📱 Configurando WhatsApp para: {$tenant->nome}\n";
echo "Digite o número de WhatsApp (formato: +5531999887766): ";
$whatsappNumber = trim(fgets(STDIN));

// Validar formato
if (!preg_match('/^\+?[0-9]{12,15}$/', $whatsappNumber)) {
    echo "❌ Formato inválido! Use o formato: +5531999887766\n";
    exit(1);
}

// Adicionar + se não tiver
if (!str_starts_with($whatsappNumber, '+')) {
    $whatsappNumber = '+' . $whatsappNumber;
}

// Verificar se já existe configuração
$config = DB::table('tenant_configs')->where('tenant_id', $tenantId)->first();

if ($config) {
    // Atualizar
    DB::table('tenant_configs')
        ->where('tenant_id', $tenantId)
        ->update([
            'whatsapp_number' => $whatsappNumber,
            'updated_at' => now()
        ]);
    echo "✅ Número de WhatsApp atualizado com sucesso!\n";
} else {
    // Criar
    DB::table('tenant_configs')->insert([
        'tenant_id' => $tenantId,
        'whatsapp_number' => $whatsappNumber,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "✅ Número de WhatsApp configurado com sucesso!\n";
}

echo "\n📊 Configuração atual:\n";
$currentConfig = DB::table('tenant_configs')->where('tenant_id', $tenantId)->first();
echo "  Tenant: {$tenant->nome}\n";
echo "  WhatsApp: {$currentConfig->whatsapp_number}\n";
echo "\n";
