<?php

require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Lead;
use Illuminate\Support\Facades\DB;

echo "=== VERIFICAR PERDA DE DADOS DO CHAVES NA MÃO ===\n\n";

// Buscar leads que têm dados nas colunas de preferências
$leadsComDados = DB::table('leads')
    ->select([
        'id', 'nome', 'email',
        'estado_civil', 'composicao_familiar',
        'fonte_renda', 'financiamento_status',
        'prazo_compra', 'objetivo_compra',
        'preferencia_tipo_imovel', 'preferencia_bairro',
        'preferencia_lazer', 'preferencia_seguranca',
        'caracteristicas_desejadas', 'observacoes_cliente',
        'budget_min', 'budget_max',
        'pessoa_id'
    ])
    ->where(function($q) {
        $q->whereNotNull('estado_civil')
          ->orWhereNotNull('composicao_familiar')
          ->orWhereNotNull('fonte_renda')
          ->orWhereNotNull('financiamento_status')
          ->orWhereNotNull('prazo_compra')
          ->orWhereNotNull('objetivo_compra')
          ->orWhereNotNull('preferencia_tipo_imovel')
          ->orWhereNotNull('preferencia_bairro')
          ->orWhereNotNull('preferencia_lazer')
          ->orWhereNotNull('preferencia_seguranca')
          ->orWhereNotNull('caracteristicas_desejadas')
          ->orWhereNotNull('observacoes_cliente')
          ->orWhereNotNull('budget_min')
          ->orWhereNotNull('budget_max');
    })
    ->limit(5)
    ->get();

echo "Total de leads com dados preenchidos: " . DB::table('leads')
    ->where(function($q) {
        $q->whereNotNull('estado_civil')
          ->orWhereNotNull('composicao_familiar')
          ->orWhereNotNull('fonte_renda')
          ->orWhereNotNull('preferencia_tipo_imovel');
    })
    ->count() . "\n\n";

if ($leadsComDados->isEmpty()) {
    echo "❌ NENHUM lead encontrado com dados nessas colunas.\n";
    echo "Isso pode indicar que:\n";
    echo "  1. Os dados do Chaves na Mão não estão sendo salvos\n";
    echo "  2. A integração não está enviando esses campos\n";
    echo "  3. Os campos têm nomes diferentes\n\n";
    
    // Verificar se tem algum lead com observacoes preenchidas
    $leadsComObs = DB::table('leads')
        ->whereNotNull('observacoes')
        ->where('observacoes', '!=', '')
        ->limit(3)
        ->get(['id', 'nome', 'observacoes', 'observacoes_cliente']);
    
    if (!$leadsComObs->isEmpty()) {
        echo "\n--- LEADS COM OBSERVAÇÕES ---\n";
        foreach ($leadsComObs as $lead) {
            echo "\nLead ID: {$lead->id}\n";
            echo "Nome: {$lead->nome}\n";
            echo "Observações: " . substr($lead->observacoes, 0, 200) . "\n";
            if ($lead->observacoes_cliente) {
                echo "Obs Cliente: {$lead->observacoes_cliente}\n";
            }
        }
    }
} else {
    echo "✓ Encontrados " . count($leadsComDados) . " leads com dados:\n\n";
    
    foreach ($leadsComDados as $lead) {
        echo "========================================\n";
        echo "Lead ID: {$lead->id}\n";
        echo "Nome: {$lead->nome}\n";
        echo "Pessoa ID: " . ($lead->pessoa_id ?? 'NULL') . "\n\n";
        
        echo "Dados pessoais:\n";
        if ($lead->estado_civil) echo "  - Estado Civil: {$lead->estado_civil}\n";
        if ($lead->composicao_familiar) echo "  - Composição Familiar: {$lead->composicao_familiar}\n";
        if ($lead->fonte_renda) echo "  - Fonte de Renda: {$lead->fonte_renda}\n";
        
        echo "\nDados financeiros:\n";
        if ($lead->financiamento_status) echo "  - Status Financiamento: {$lead->financiamento_status}\n";
        if ($lead->budget_min) echo "  - Orçamento Mín: R$ {$lead->budget_min}\n";
        if ($lead->budget_max) echo "  - Orçamento Máx: R$ {$lead->budget_max}\n";
        
        echo "\nPreferências:\n";
        if ($lead->preferencia_tipo_imovel) echo "  - Tipo Imóvel: {$lead->preferencia_tipo_imovel}\n";
        if ($lead->preferencia_bairro) echo "  - Bairro: {$lead->preferencia_bairro}\n";
        if ($lead->preferencia_lazer) echo "  - Lazer: {$lead->preferencia_lazer}\n";
        if ($lead->preferencia_seguranca) echo "  - Segurança: {$lead->preferencia_seguranca}\n";
        
        echo "\nTimeline e objetivos:\n";
        if ($lead->prazo_compra) echo "  - Prazo Compra: {$lead->prazo_compra}\n";
        if ($lead->objetivo_compra) echo "  - Objetivo: {$lead->objetivo_compra}\n";
        
        if ($lead->caracteristicas_desejadas) {
            echo "\nCaracterísticas Desejadas:\n";
            echo "  " . substr($lead->caracteristicas_desejadas, 0, 200) . "...\n";
        }
        
        if ($lead->observacoes_cliente) {
            echo "\nObservações do Cliente:\n";
            echo "  " . substr($lead->observacoes_cliente, 0, 200) . "...\n";
        }
        
        // Se tem pessoa, verificar se dados foram copiados
        if ($lead->pessoa_id) {
            $pessoa = DB::table('pessoas')->find($lead->pessoa_id);
            if ($pessoa) {
                echo "\n--- DADOS NA PESSOA ---\n";
                echo "Observações: " . ($pessoa->observacoes ? substr($pessoa->observacoes, 0, 100) : 'VAZIO') . "\n";
                echo "Preferências: " . ($pessoa->preferencias ? substr($pessoa->preferencias, 0, 100) : 'VAZIO') . "\n";
                echo "Profissão: " . ($pessoa->profissao ?? 'VAZIO') . "\n";
                echo "Renda: " . ($pessoa->renda_mensal ?? 'VAZIO') . "\n";
            }
        }
        
        echo "\n";
    }
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";
