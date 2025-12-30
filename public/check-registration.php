<?php
/**
 * Verificar registros criados em produção
 */

$token = $_GET['token'] ?? '';
if ($token !== 'check-2025') {
    http_response_code(403);
    die('Access denied');
}

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/../bootstrap/app.php';
DB::connection()->getPdo();

header('Content-Type: text/plain; charset=utf-8');
echo "═══════════════════════════════════════════════════════════════\n";
echo "VERIFICAÇÃO EM PRODUÇÃO: Lead #33 - Marcus Lima\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Verificar Lead
echo "1. LEAD:\n";
$lead = DB::table('leads')->where('id', 33)->first();
if ($lead) {
    echo "   ✅ Lead encontrado\n";
    echo "   ID: {$lead->id}\n";
    echo "   Nome: {$lead->nome}\n";
    echo "   Email: " . ($lead->email ?? 'não informado') . "\n";
    echo "   Telefone: {$lead->telefone}\n";
    echo "   Status: {$lead->status}\n";
    echo "   WhatsApp Name: " . ($lead->whatsapp_name ?? 'N/A') . "\n";
    echo "   Tenant ID: {$lead->tenant_id}\n";
    echo "   Criado em: {$lead->created_at}\n\n";
} else {
    echo "   ❌ Lead #33 não encontrado\n";
    // Buscar último lead criado
    $lastLead = DB::table('leads')->orderBy('id', 'desc')->first();
    if ($lastLead) {
        echo "   Último lead criado: #{$lastLead->id} - {$lastLead->nome} ({$lastLead->created_at})\n";
    }
    echo "\n";
}

// 2. Verificar Conversa
echo "2. CONVERSA:\n";
$conversa = DB::table('conversas')->where('id', 25)->first();
if ($conversa) {
    echo "   ✅ Conversa encontrada\n";
    echo "   ID: {$conversa->id}\n";
    echo "   Telefone: {$conversa->telefone}\n";
    echo "   WhatsApp Name: " . ($conversa->whatsapp_name ?? 'N/A') . "\n";
    echo "   Lead ID: " . ($conversa->lead_id ?? 'não vinculado') . "\n";
    echo "   User ID: " . ($conversa->user_id ?? 'não vinculado') . "\n";
    echo "   Status: " . ($conversa->status ?? 'ativo') . "\n";
    echo "   Tenant ID: {$conversa->tenant_id}\n";
    echo "   Criado em: {$conversa->created_at}\n\n";
    
    // Verificar mensagens
    try {
        $mensagens = DB::table('mensagens')->where('conversa_id', 25)->orderBy('created_at')->get();
        echo "   📱 Mensagens ({$mensagens->count()}):\n";
        if ($mensagens->count() > 0) {
            foreach ($mensagens as $msg) {
                $tipo = $msg->tipo === 'received' ? '📥 Cliente' : '📤 IA';
                $preview = strlen($msg->mensagem) > 60 ? substr($msg->mensagem, 0, 60) . '...' : $msg->mensagem;
                echo "      [{$msg->created_at}] {$tipo}: {$preview}\n";
            }
        } else {
            echo "      (nenhuma mensagem)\n";
        }
    } catch (\Exception $e) {
        echo "   ⚠️ Erro ao buscar mensagens: " . $e->getMessage() . "\n";
    }
    echo "\n";
} else {
    echo "   ❌ Conversa #25 não encontrada\n";
    // Buscar última conversa
    $lastConv = DB::table('conversas')->orderBy('id', 'desc')->first();
    if ($lastConv) {
        echo "   Última conversa: #{$lastConv->id} - {$lastConv->telefone} ({$lastConv->created_at})\n";
    }
    echo "\n";
}

// 3. Verificar User/Cliente
echo "3. CADASTRO DO CLIENTE:\n";
if (isset($conversa) && $conversa && $conversa->user_id) {
    $user = DB::table('users')->where('id', $conversa->user_id)->first();
    if ($user) {
        echo "   ✅ Cliente cadastrado\n";
        echo "   ID: {$user->id}\n";
        echo "   Nome: {$user->name}\n";
        echo "   Email: " . ($user->email ?? 'não informado') . "\n";
        echo "   Telefone: " . ($user->telefone ?? 'não informado') . "\n";
        echo "   Tipo: " . ($user->tipo ?? 'cliente') . "\n";
        echo "   Tenant ID: {$user->tenant_id}\n";
        echo "   Criado em: {$user->created_at}\n\n";
    } else {
        echo "   ❌ Cliente não encontrado (ID: {$conversa->user_id})\n\n";
    }
} else {
    echo "   ⚠️ Conversa não tem user_id vinculado\n\n";
}

// 4. Resumo Final
echo "═══════════════════════════════════════════════════════════════\n";
echo "RESUMO FINAL:\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "✓ Lead criado: " . (isset($lead) && $lead ? '✅ SIM' : '❌ NÃO') . "\n";
echo "✓ Conversa criada: " . (isset($conversa) && $conversa ? '✅ SIM' : '❌ NÃO') . "\n";
echo "✓ Cliente cadastrado: " . (isset($user) && $user ? '✅ SIM' : '❌ NÃO') . "\n";
echo "✓ Mensagens: " . (isset($mensagens) ? $mensagens->count() : 0) . "\n";
echo "\n";
