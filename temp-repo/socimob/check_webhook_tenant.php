<?php
/**
 * Verificar configuração de tenant para webhook
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';

// Resolver conexão de banco
DB::connection()->getPdo();

echo "═══════════════════════════════════════════════════════════════\n";
echo "DIAGNÓSTICO: Tenant para Webhook\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Verificar domínio
echo "1. Verificando domínio 'exclusivalarimoveis.com':\n";
$tenant = DB::table('tenants')->where('domain', 'exclusivalarimoveis.com')->first();
if ($tenant) {
    echo "   ✅ Tenant encontrado: {$tenant->name} (ID: {$tenant->id})\n\n";
} else {
    echo "   ❌ Nenhum tenant com este domínio\n\n";
}

// 2. Verificar número WhatsApp
echo "2. Verificando número WhatsApp '+553173341150':\n";
$configs = DB::table('tenant_configs')->whereNotNull('twilio_whatsapp_from')->get();
$found = false;
foreach ($configs as $config) {
    $configNumber = preg_replace('/[^0-9]/', '', $config->twilio_whatsapp_from);
    if ($configNumber === '553173341150') {
        $tenantInfo = DB::table('tenants')->find($config->tenant_id);
        echo "   ✅ Encontrado em: {$tenantInfo->name} (ID: {$config->tenant_id})\n";
        echo "      Config: {$config->twilio_whatsapp_from}\n\n";
        $found = true;
        break;
    }
}
if (!$found) {
    echo "   ❌ Nenhum tenant com este número configurado\n\n";
}

// 3. Listar todos os tenants
echo "3. Todos os tenants cadastrados:\n";
$allTenants = DB::table('tenants')->get();
foreach ($allTenants as $t) {
    $config = DB::table('tenant_configs')->where('tenant_id', $t->id)->first();
    $whatsapp = $config ? $config->twilio_whatsapp_from : null;
    echo "   - {$t->name} (ID: {$t->id})\n";
    echo "     Domínio: " . ($t->domain ?: 'não configurado') . "\n";
    echo "     WhatsApp: " . ($whatsapp ?: 'não configurado') . "\n\n";
}

// 4. Verificar .env
echo "4. Variável WEBHOOK_TENANT_ID no .env:\n";
$webhookTenantId = env('WEBHOOK_TENANT_ID');
if ($webhookTenantId) {
    echo "   ✅ Configurado: {$webhookTenantId}\n";
    $t = DB::table('tenants')->find($webhookTenantId);
    if ($t) {
        echo "   ✅ Tenant válido: {$t->name}\n\n";
    } else {
        echo "   ❌ Tenant não existe!\n\n";
    }
} else {
    echo "   ⚠️ Não configurado\n\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "RECOMENDAÇÃO:\n";
echo "═══════════════════════════════════════════════════════════════\n";

if (!$tenant) {
    echo "1. Atualizar domínio do tenant Exclusiva para 'exclusivalarimoveis.com'\n";
    echo "   OU\n";
}

if (!$found) {
    echo "2. Configurar número '+553173341150' no twilio_whatsapp_from\n";
    echo "   OU\n";
}

if (!$webhookTenantId) {
    echo "3. Adicionar WEBHOOK_TENANT_ID no .env de produção\n";
}

echo "\n";
