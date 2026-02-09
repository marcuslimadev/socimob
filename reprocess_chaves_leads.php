<?php

require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Lead;
use App\Models\Pessoa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== RE-PROCESSAR LEADS DO CHAVES NA MÃO ===\n\n";

// Buscar todos os leads que têm observações mencionando "Chaves na Mão"
$leads = Lead::where('observacoes', 'LIKE', '%Chaves na M%')
    ->whereNotNull('pessoa_id')
    ->orderBy('created_at', 'desc')
    ->get();

echo "Total de leads encontrados: " . count($leads) . "\n\n";

if ($leads->isEmpty()) {
    echo "Nenhum lead do Chaves na Mão encontrado.\n";
    exit(0);
}

$sucessos = 0;
$erros = 0;
$pulos = 0;

foreach ($leads as $lead) {
    echo "----------------------------------------\n";
    echo "Lead ID: {$lead->id} - {$lead->nome}\n";
    
    try {
        if (!$lead->pessoa_id) {
            echo "  ⚠️  Lead sem pessoa associada - pulando\n";
            $pulos++;
            continue;
        }
        
        $pessoa = Pessoa::withoutGlobalScope('tenant')->find($lead->pessoa_id);
        if (!$pessoa) {
            echo "  ❌ Pessoa ID {$lead->pessoa_id} não encontrada\n";
            $erros++;
            continue;
        }
        
        echo "  Pessoa: {$pessoa->nome} (ID: {$pessoa->id})\n";
        
        // Forçar atualização da pessoa a partir do lead
        // Vamos usar os métodos do LeadObserver
        $updates = [];
        
        // Montar observações (adicionando ao histórico existente)
        $observacoes = [];
        
        if (!empty($lead->observacoes)) {
            $observacoes[] = $lead->observacoes;
        }
        
        if (!empty($lead->observacoes_cliente)) {
            $observacoes[] = "Observações do Cliente: {$lead->observacoes_cliente}";
        }
        
        if (!empty($lead->estado_civil)) {
            $observacoes[] = "Estado Civil: {$lead->estado_civil}";
        }
        if (!empty($lead->composicao_familiar)) {
            $observacoes[] = "Composição Familiar: {$lead->composicao_familiar}";
        }
        if (!empty($lead->fonte_renda)) {
            $observacoes[] = "Fonte de Renda: {$lead->fonte_renda}";
        }
        if (!empty($lead->financiamento_status)) {
            $observacoes[] = "Status Financiamento: {$lead->financiamento_status}";
        }
        if (!empty($lead->prazo_compra)) {
            $observacoes[] = "Prazo de Compra: {$lead->prazo_compra}";
        }
        if (!empty($lead->objetivo_compra)) {
            $observacoes[] = "Objetivo da Compra: {$lead->objetivo_compra}";
        }
        
        if (!empty($observacoes)) {
            $novasObs = implode("\n\n", $observacoes);
            if (!empty($pessoa->observacoes) && !str_contains($pessoa->observacoes, $lead->observacoes ?? '')) {
                $updates['observacoes'] = $pessoa->observacoes . "\n\n--- Atualização Automática ---\n" . $novasObs;
            } else {
                $updates['observacoes'] = $novasObs;
            }
        }
        
        // Montar preferências
        $preferencias = [];
        
        if (!empty($lead->budget_min)) {
            $preferencias['budget_min'] = $lead->budget_min;
        }
        if (!empty($lead->budget_max)) {
            $preferencias['budget_max'] = $lead->budget_max;
        }
        if (!empty($lead->localizacao)) {
            $preferencias['localizacao'] = $lead->localizacao;
        }
        if (!empty($lead->preferencia_bairro)) {
            $preferencias['bairro'] = $lead->preferencia_bairro;
        }
        if (!empty($lead->preferencia_tipo_imovel)) {
            $preferencias['tipo_imovel'] = $lead->preferencia_tipo_imovel;
        }
        if (!empty($lead->quartos)) {
            $preferencias['quartos'] = $lead->quartos;
        }
        if (!empty($lead->suites)) {
            $preferencias['suites'] = $lead->suites;
        }
        if (!empty($lead->garagem)) {
            $preferencias['garagem'] = $lead->garagem;
        }
        if (!empty($lead->preferencia_lazer)) {
            $preferencias['lazer'] = $lead->preferencia_lazer;
        }
        if (!empty($lead->preferencia_seguranca)) {
            $preferencias['seguranca'] = $lead->preferencia_seguranca;
        }
        if (!empty($lead->caracteristicas_desejadas)) {
            $preferencias['caracteristicas'] = $lead->caracteristicas_desejadas;
        }
        
        if (!empty($preferencias)) {
            $prefAtuais = $pessoa->preferencias;
            if (is_string($prefAtuais)) {
                $prefAtuais = json_decode($prefAtuais, true) ?? [];
            }
            $updates['preferencias'] = array_merge($prefAtuais ?? [], $preferencias);
        }
        
        // Atualizar campos básicos se vazios
        if (empty($pessoa->profissao) && !empty($lead->profissao)) {
            $updates['profissao'] = $lead->profissao;
        }
        if (empty($pessoa->renda_mensal) && !empty($lead->renda_mensal)) {
            $updates['renda_mensal'] = $lead->renda_mensal;
        }
        
        if (!empty($updates)) {
            $pessoa->update($updates);
            echo "  ✅ Pessoa atualizada com sucesso\n";
            if (isset($updates['preferencias'])) {
                echo "     - Preferências: " . count($updates['preferencias']) . " campos\n";
            }
            $sucessos++;
        } else {
            echo "  ℹ️  Nenhuma atualização necessária\n";
            $pulos++;
        }
        
    } catch (\Exception $e) {
        echo "  ❌ ERRO: " . $e->getMessage() . "\n";
        $erros++;
    }
}

echo "\n========================================\n";
echo "RESUMO\n";
echo "========================================\n";
echo "✅ Sucessos: {$sucessos}\n";
echo "⏭️  Pulados: {$pulos}\n";
echo "❌ Erros: {$erros}\n";
echo "\n=== FIM DO PROCESSAMENTO ===\n";
