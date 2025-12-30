<?php
/**
 * Corrigir tenant em produção via web
 * REMOVER após uso!
 */

$token = $_GET['token'] ?? '';
if ($token !== 'fix-tenant-2025') {
    http_response_code(403);
    die('Access denied');
}

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/../bootstrap/app.php';
DB::connection()->getPdo();

header('Content-Type: text/plain; charset=utf-8');
echo "═══════════════════════════════════════════════════════════════\n";
echo "CORRIGINDO TENANT EM PRODUÇÃO\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Verificar estado atual
echo "1. Estado ANTES da correção:\n";
$tenant = DB::table('tenants')->find(1);
$config = DB::table('tenant_configs')->where('tenant_id', 1)->first();

if ($tenant) {
    echo "   Tenant: {$tenant->name}\n";
    echo "   Domínio: " . ($tenant->domain ?? 'não configurado') . "\n";
} else {
    echo "   ❌ Tenant ID 1 não encontrado!\n";
}

if ($config) {
    echo "   WhatsApp: " . ($config->twilio_whatsapp_from ?? 'não configurado') . "\n\n";
} else {
    echo "   ❌ Config não encontrada!\n\n";
}

// 2. Aplicar correção
echo "2. Aplicando correção...\n";

$updated = 0;

if ($tenant && $tenant->domain !== 'exclusivalarimoveis.com') {
    DB::table('tenants')->where('id', 1)->update([
        'domain' => 'exclusivalarimoveis.com',
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    echo "   ✅ Domínio atualizado\n";
    $updated++;
}

if ($config && $config->twilio_whatsapp_from !== 'whatsapp:+553173341150') {
    DB::table('tenant_configs')->where('tenant_id', 1)->update([
        'twilio_whatsapp_from' => 'whatsapp:+553173341150',
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    echo "   ✅ WhatsApp atualizado\n";
    $updated++;
}

if ($updated === 0) {
    echo "   ℹ️ Nenhuma alteração necessária (já está correto)\n";
}

echo "\n";

// 3. Verificar estado final
echo "3. Estado DEPOIS da correção:\n";
$tenant = DB::table('tenants')->find(1);
$config = DB::table('tenant_configs')->where('tenant_id', 1)->first();

echo "   Tenant: {$tenant->name}\n";
echo "   Domínio: {$tenant->domain}\n";
echo "   WhatsApp: {$config->twilio_whatsapp_from}\n\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "✅ CONCLUÍDO!\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\nAgora teste enviando uma mensagem para +553173341150\n\n";
