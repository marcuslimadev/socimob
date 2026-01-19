<?php
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Lumen
$app = require __DIR__ . '/bootstrap/app.php';

echo "=== TESTE PEGAR ATENDIMENTO ===\n\n";

// Configurar DB
$app->withFacades();

try {
    // Buscar usuário ativo
    $user = DB::table('users')
        ->where('tenant_id', 1)
        ->where('email', 'contato@exclusivalarimoveis.com.br')
        ->first();
    
    if (!$user) {
        echo "❌ Usuário não encontrado\n";
        exit(1);
    }
    
    echo "✅ Usuário encontrado: {$user->email}\n";
    echo "   User ID: {$user->id}\n\n";
    
    // Buscar um lead disponível
    $lead = DB::table('leads')
        ->where('tenant_id', 1)
        ->whereNull('corretor_id')
        ->first();
    
    if (!$lead) {
        echo "❌ Nenhum lead disponível\n";
        exit(1);
    }
    
    echo "✅ Lead encontrado: ID {$lead->id}\n";
    echo "   Lead ID: {$lead->id}\n";
    echo "   Status atual: {$lead->status}\n";
    echo "   Corretor atual: " . ($lead->corretor_id ?? 'nenhum') . "\n\n";
    
    // Atualizar lead diretamente (simular claim)
    echo "Testando claim...\n";
    
    $affected = DB::table('leads')
        ->where('id', $lead->id)
        ->update([
            'corretor_id' => $user->id,
            'status' => 'em_atendimento',
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    
    if ($affected) {
        echo "✅ Lead atribuído com sucesso!\n";
        
        // Verificar
        $leadAtualizado = DB::table('leads')->find($lead->id);
        echo "\nStatus após claim:\n";
        echo "   Corretor ID: {$leadAtualizado->corretor_id}\n";
        echo "   Status: {$leadAtualizado->status}\n";
    } else {
        echo "❌ Falha ao atribuir lead\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . "\n";
    echo "   Linha: " . $e->getLine() . "\n";
}
