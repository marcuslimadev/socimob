<?php
/**
 * Corrigir configuração do tenant Exclusiva para produção
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
DB::connection()->getPdo();

echo "═══════════════════════════════════════════════════════════════\n";
echo "ATUALIZANDO TENANT EXCLUSIVA PARA PRODUÇÃO\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Atualizar domínio
echo "1. Atualizando domínio...\n";
DB::table('tenants')
    ->where('id', 1)
    ->update([
        'domain' => 'exclusivalarimoveis.com',
        'updated_at' => date('Y-m-d H:i:s')
    ]);
echo "   ✅ Domínio atualizado para 'exclusivalarimoveis.com'\n\n";

// 2. Atualizar número WhatsApp
echo "2. Atualizando número WhatsApp...\n";
DB::table('tenant_configs')
    ->where('tenant_id', 1)
    ->update([
        'twilio_whatsapp_from' => 'whatsapp:+553173341150',
        'updated_at' => date('Y-m-d H:i:s')
    ]);
echo "   ✅ Número atualizado para 'whatsapp:+553173341150'\n\n";

// 3. Verificar resultado
echo "3. Verificando configuração final:\n";
$tenant = DB::table('tenants')->find(1);
$config = DB::table('tenant_configs')->where('tenant_id', 1)->first();

echo "   Tenant: {$tenant->name}\n";
echo "   Domínio: {$tenant->domain}\n";
echo "   WhatsApp: {$config->twilio_whatsapp_from}\n\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "✅ ATUALIZAÇÃO CONCLUÍDA!\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\nAgora teste enviando uma mensagem para +553173341150\n\n";
