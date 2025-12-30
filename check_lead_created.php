<?php
/**
 * Verificar se lead, cliente e conversa foram criados
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
DB::connection()->getPdo();

echo "═══════════════════════════════════════════════════════════════\n";
echo "VERIFICAÇÃO: Lead #33 - Marcus Lima\n";
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
    echo "   WhatsApp Name: " . ($lead->whatsapp_name ?? 'não informado') . "\n";
    echo "   Criado em: {$lead->created_at}\n\n";
} else {
    echo "   ❌ Lead não encontrado\n\n";
}

// 2. Verificar Conversa
echo "2. CONVERSA:\n";
$conversa = DB::table('conversas')->where('id', 25)->first();
if ($conversa) {
    echo "   ✅ Conversa encontrada\n";
    echo "   ID: {$conversa->id}\n";
    echo "   Telefone: {$conversa->telefone}\n";
    echo "   WhatsApp Name: " . ($conversa->whatsapp_name ?? 'não informado') . "\n";
    echo "   Lead ID: " . ($conversa->lead_id ?? 'não vinculado') . "\n";
    echo "   User ID: " . ($conversa->user_id ?? 'não vinculado') . "\n";
    echo "   Status: " . ($conversa->status ?? 'ativo') . "\n";
    echo "   Criado em: {$conversa->created_at}\n\n";
    
    // Verificar mensagens
    $mensagens = DB::table('mensagens')->where('conversa_id', 25)->orderBy('created_at')->get();
    echo "   Mensagens ({$mensagens->count()}):\n";
    foreach ($mensagens as $msg) {
        $tipo = $msg->tipo === 'received' ? '📥' : '📤';
        $de = $msg->tipo === 'received' ? 'Cliente' : 'IA';
        echo "     {$tipo} [{$msg->created_at}] {$de}: " . substr($msg->mensagem, 0, 60) . "...\n";
    }
    echo "\n";
} else {
    echo "   ❌ Conversa não encontrada\n\n";
}

// 3. Verificar User/Cliente
echo "3. CADASTRO DO CLIENTE (users):\n";
if ($conversa && $conversa->user_id) {
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

// 4. Resumo
echo "═══════════════════════════════════════════════════════════════\n";
echo "RESUMO:\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "Lead criado: " . ($lead ? '✅' : '❌') . "\n";
echo "Conversa criada: " . ($conversa ? '✅' : '❌') . "\n";
echo "Cliente cadastrado: " . (isset($user) && $user ? '✅' : '❌') . "\n";
echo "Mensagens trocadas: " . (isset($mensagens) ? $mensagens->count() : 0) . "\n";
echo "\n";
