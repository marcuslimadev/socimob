<?php
/**
 * Corrigir conversa #25 vinculando user_id
 */

$token = $_GET['token'] ?? '';
if ($token !== 'fix-conv-2025') {
    http_response_code(403);
    die('Access denied');
}

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/../bootstrap/app.php';
DB::connection()->getPdo();

header('Content-Type: text/plain; charset=utf-8');
echo "═══════════════════════════════════════════════════════════════\n";
echo "CORRIGINDO CONVERSA #25\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Buscar conversa e lead
$conversa = DB::table('conversas')->find(25);
$lead = $conversa ? DB::table('leads')->find($conversa->lead_id) : null;

if (!$conversa) {
    die("❌ Conversa #25 não encontrada\n");
}

if (!$lead) {
    die("❌ Lead #{$conversa->lead_id} não encontrado\n");
}

echo "📋 Conversa: #{$conversa->id} - {$conversa->telefone}\n";
echo "📋 Lead: #{$lead->id} - {$lead->nome}\n";
echo "📋 User atual: " . ($lead->user_id ?? 'não vinculado') . "\n\n";

// 2. Buscar ou criar user
$userId = $lead->user_id;

if (!$userId) {
    echo "Criando cliente...\n";
    
    // Criar email placeholder
    $email = "lead-{$lead->tenant_id}-{$lead->id}@no-email.local";
    
    // Criar user
    $userId = DB::table('users')->insertGetId([
        'name' => $lead->nome ?: 'Cliente WhatsApp',
        'email' => $email,
        'password' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
        'role' => 'client',
        'is_active' => 1,
        'tenant_id' => $lead->tenant_id,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "✅ Cliente criado: ID {$userId}\n";
    
    // Atualizar lead
    DB::table('leads')->where('id', $lead->id)->update([
        'user_id' => $userId,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "✅ Lead atualizado com user_id\n";
}

// 3. Atualizar conversa
if (!$conversa->user_id) {
    DB::table('conversas')->where('id', 25)->update([
        'user_id' => $userId,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "✅ Conversa vinculada ao cliente\n\n";
} else {
    echo "ℹ️ Conversa já tinha user_id: {$conversa->user_id}\n\n";
}

// 4. Verificar resultado final
$conversaFinal = DB::table('conversas')->find(25);
$leadFinal = DB::table('leads')->find($lead->id);
$user = DB::table('users')->find($userId);

echo "═══════════════════════════════════════════════════════════════\n";
echo "RESULTADO FINAL:\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "✓ Lead #{$leadFinal->id}: user_id = {$leadFinal->user_id}\n";
echo "✓ Conversa #{$conversaFinal->id}: user_id = {$conversaFinal->user_id}\n";
echo "✓ Cliente: {$user->name} ({$user->email})\n";
echo "\n✅ Tudo vinculado corretamente!\n\n";
