<?php

require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Lead;
use App\Models\Pessoa;
use Illuminate\Support\Facades\DB;

echo "=== ANÁLISE DO LEAD DA PATRÍCIA ===\n\n";

// Buscar por telefone, email ou nome
$leads = Lead::where(function($q) {
    $q->where('telefone', 'LIKE', '%99501%7002%')
      ->orWhere('whatsapp', 'LIKE', '%99501%7002%')
      ->orWhere('email', 'LIKE', '%patvilaverde%')
      ->orWhere('nome', 'LIKE', '%Patrícia%')
      ->orWhere('nome', 'LIKE', '%Patricia%');
})->get();

if ($leads->isEmpty()) {
    echo "❌ LEAD NÃO ENCONTRADO!\n";
    echo "Procurei por:\n";
    echo "  - Telefone: 99501-7002\n";
    echo "  - Email: patvilaverde@yahoo.com.br\n";
    echo "  - Nome: Patrícia/Patricia\n\n";
    
    // Listar últimos 10 leads criados
    echo "--- ÚLTIMOS 10 LEADS CRIADOS ---\n";
    $ultimosLeads = Lead::orderBy('created_at', 'desc')->limit(10)->get(['id', 'nome', 'telefone', 'email', 'created_at']);
    foreach ($ultimosLeads as $lead) {
        echo "ID {$lead->id}: {$lead->nome} - {$lead->telefone} - {$lead->email} - {$lead->created_at}\n";
    }
    exit(1);
}

echo "✓ Encontrados " . count($leads) . " lead(s):\n\n";

foreach ($leads as $lead) {
    echo "========================================\n";
    echo "LEAD ID: {$lead->id}\n";
    echo "========================================\n\n";
    
    echo "--- DADOS BÁSICOS ---\n";
    echo "Nome: " . ($lead->nome ?? 'VAZIO') . "\n";
    echo "Email: " . ($lead->email ?? 'VAZIO') . "\n";
    echo "Telefone: " . ($lead->telefone ?? 'VAZIO') . "\n";
    echo "WhatsApp: " . ($lead->whatsapp ?? 'VAZIO') . "\n";
    echo "CPF: " . ($lead->cpf ?? 'VAZIO') . "\n";
    echo "Status: " . ($lead->status ?? 'VAZIO') . "\n";
    echo "Criado em: {$lead->created_at}\n\n";
    
    echo "--- DADOS PESSOAIS ---\n";
    echo "Profissão: " . ($lead->profissao ?? 'VAZIO') . "\n";
    echo "Renda Mensal: " . ($lead->renda_mensal ?? 'VAZIO') . "\n";
    echo "Estado Civil: " . ($lead->estado_civil ?? 'VAZIO') . "\n";
    echo "Composição Familiar: " . ($lead->composicao_familiar ?? 'VAZIO') . "\n";
    echo "Fonte de Renda: " . ($lead->fonte_renda ?? 'VAZIO') . "\n\n";
    
    echo "--- DADOS FINANCEIROS ---\n";
    echo "Budget Mínimo: " . ($lead->budget_min ? "R$ {$lead->budget_min}" : 'VAZIO') . "\n";
    echo "Budget Máximo: " . ($lead->budget_max ? "R$ {$lead->budget_max}" : 'VAZIO') . "\n";
    echo "Status Financiamento: " . ($lead->financiamento_status ?? 'VAZIO') . "\n\n";
    
    echo "--- PREFERÊNCIAS DO IMÓVEL ---\n";
    echo "Tipo de Imóvel: " . ($lead->preferencia_tipo_imovel ?? 'VAZIO') . "\n";
    echo "Bairro: " . ($lead->preferencia_bairro ?? 'VAZIO') . "\n";
    echo "Localização: " . ($lead->localizacao ?? 'VAZIO') . "\n";
    echo "Quartos: " . ($lead->quartos ?? 'VAZIO') . "\n";
    echo "Suítes: " . ($lead->suites ?? 'VAZIO') . "\n";
    echo "Garagem: " . ($lead->garagem ?? 'VAZIO') . "\n";
    echo "Preferência Lazer: " . ($lead->preferencia_lazer ?? 'VAZIO') . "\n";
    echo "Preferência Segurança: " . ($lead->preferencia_seguranca ?? 'VAZIO') . "\n\n";
    
    echo "--- TIMELINE E OBJETIVO ---\n";
    echo "Prazo de Compra: " . ($lead->prazo_compra ?? 'VAZIO') . "\n";
    echo "Objetivo da Compra: " . ($lead->objetivo_compra ?? 'VAZIO') . "\n\n";
    
    echo "--- OBSERVAÇÕES ---\n";
    if ($lead->observacoes) {
        echo "Observações: " . substr($lead->observacoes, 0, 500) . "\n\n";
    } else {
        echo "Observações: VAZIO\n\n";
    }
    
    if ($lead->observacoes_cliente) {
        echo "Observações do Cliente: " . substr($lead->observacoes_cliente, 0, 500) . "\n\n";
    } else {
        echo "Observações do Cliente: VAZIO\n\n";
    }
    
    if ($lead->caracteristicas_desejadas) {
        echo "Características Desejadas: " . substr($lead->caracteristicas_desejadas, 0, 500) . "\n\n";
    } else {
        echo "Características Desejadas: VAZIO\n\n";
    }
    
    echo "--- INTEGRAÇÃO CHAVES NA MÃO ---\n";
    echo "Status Envio: " . ($lead->chaves_na_mao_status ?? 'VAZIO') . "\n";
    echo "Enviado em: " . ($lead->chaves_na_mao_sent_at ?? 'VAZIO') . "\n";
    if ($lead->chaves_na_mao_response) {
        echo "Resposta: " . substr($lead->chaves_na_mao_response, 0, 200) . "...\n";
    }
    if ($lead->chaves_na_mao_error) {
        echo "Erro: " . substr($lead->chaves_na_mao_error, 0, 200) . "...\n";
    }
    echo "\n";
    
    // Verificar pessoa associada
    echo "--- PESSOA ASSOCIADA ---\n";
    if ($lead->pessoa_id) {
        $pessoa = Pessoa::withoutGlobalScope('tenant')->find($lead->pessoa_id);
        if ($pessoa) {
            echo "✓ Pessoa ID: {$pessoa->id}\n";
            echo "Nome: {$pessoa->nome}\n";
            echo "Email: " . ($pessoa->email ?? 'VAZIO') . "\n";
            echo "Celular: " . ($pessoa->celular ?? 'VAZIO') . "\n";
            echo "Telefone: " . ($pessoa->telefone ?? 'VAZIO') . "\n";
            echo "WhatsApp: " . ($pessoa->whatsapp ?? 'VAZIO') . "\n";
            echo "Profissão: " . ($pessoa->profissao ?? 'VAZIO') . "\n";
            echo "Renda: " . ($pessoa->renda_mensal ?? 'VAZIO') . "\n";
            
            if ($pessoa->observacoes) {
                echo "\nObservações na Pessoa:\n" . substr($pessoa->observacoes, 0, 300) . "\n";
            } else {
                echo "\nObservações na Pessoa: VAZIO\n";
            }
            
            if ($pessoa->preferencias) {
                echo "\nPreferências na Pessoa:\n" . substr($pessoa->preferencias, 0, 300) . "\n";
            } else {
                echo "\nPreferências na Pessoa: VAZIO\n";
            }
        } else {
            echo "❌ ERRO: pessoa_id={$lead->pessoa_id} mas registro não existe!\n";
        }
    } else {
        echo "❌ SEM PESSOA ASSOCIADA\n";
    }
}

echo "\n========================================\n";
echo "DADOS ESPERADOS DO CHAVES NA MÃO:\n";
echo "========================================\n";
echo "✓ Nome: Patrícia Miriam\n";
echo "✓ Telefone: (31) 99501-7002\n";
echo "✓ Email: patvilaverde@yahoo.com.br\n";
echo "✓ Anúncio visualizado: CASA À VENDA EM ITAPOÃ COM 4 QUARTOS\n";
echo "✓ Localização: ITAPOÃ - BELO HORIZONTE/MG\n";
echo "✓ Budget: R$ 650.000,00\n";
echo "✓ Referência: TOC1318\n";
echo "✓ Quartos: 4\n";
echo "✓ Ação: Visualizou WhatsApp\n";

echo "\n=== FIM DA ANÁLISE ===\n";
