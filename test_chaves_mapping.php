<?php

/**
 * Teste de Mapeamento de Dados Chaves na Mão → Pessoa
 * 
 * Verifica se os dados do Chaves na Mão estão sendo corretamente
 * mapeados para os campos observacoes, preferencias e interesses da Pessoa
 */

require __DIR__.'/bootstrap/app.php';

use App\Models\Lead;
use App\Models\Pessoa;

// Configurar tenant
$_ENV['TENANT_ID'] = 'exclusiva';

echo "=== TESTE MAPEAMENTO CHAVES NA MÃO → PESSOA ===\n\n";

// Buscar leads recentes com dados do Chaves na Mão
$leads = Lead::where('tenant_id', 'exclusiva')
    ->whereNotNull('pessoa_id')
    ->whereNotNull('budget_min')
    ->latest()
    ->take(5)
    ->get();

if ($leads->isEmpty()) {
    echo "! Nenhum lead encontrado com dados do Chaves na Mão\n";
    echo "Buscando qualquer lead com pessoa...\n\n";
    
    $leads = Lead::where('tenant_id', 'exclusiva')
        ->whereNotNull('pessoa_id')
        ->latest()
        ->take(5)
        ->get();
}

foreach ($leads as $lead) {
    echo "--- LEAD #{$lead->id}: {$lead->nome} ---\n";
    echo "Status: {$lead->status}\n";
    echo "Telefone: {$lead->telefone}\n";
    
    if ($lead->pessoa_id) {
        $pessoa = Pessoa::find($lead->pessoa_id);
        
        if ($pessoa) {
            echo "\n✓ PESSOA #{$pessoa->id} VINCULADA\n\n";
            
            // Verificar observações
            echo "📝 OBSERVAÇÕES:\n";
            if (!empty($pessoa->observacoes)) {
                echo substr($pessoa->observacoes, 0, 200);
                if (strlen($pessoa->observacoes) > 200) echo "...";
                echo "\n";
            } else {
                echo "  (vazio)\n";
            }
            
            // Verificar preferências
            echo "\n🎯 PREFERÊNCIAS:\n";
            $preferencias = is_string($pessoa->preferencias) 
                ? json_decode($pessoa->preferencias, true) 
                : $pessoa->preferencias;
                
            if (!empty($preferencias) && is_array($preferencias)) {
                foreach ($preferencias as $key => $value) {
                    if (!empty($value)) {
                        echo "  • $key: ";
                        echo is_array($value) ? implode(', ', $value) : $value;
                        echo "\n";
                    }
                }
            } else {
                echo "  (vazio)\n";
            }
            
            // Verificar interesses
            echo "\n💡 INTERESSES:\n";
            $interesses = is_string($pessoa->interesses) 
                ? json_decode($pessoa->interesses, true) 
                : $pessoa->interesses;
                
            if (!empty($interesses) && is_array($interesses)) {
                foreach ($interesses as $interesse) {
                    echo "  • $interesse\n";
                }
            } else {
                echo "  (vazio)\n";
            }
            
            // Dados do Lead (Chaves na Mão)
            echo "\n📊 DADOS DO LEAD (Chaves na Mão):\n";
            $campos_chaves = [
                'budget_min' => 'Orçamento mínimo',
                'budget_max' => 'Orçamento máximo',
                'estado_civil' => 'Estado civil',
                'composicao_familiar' => 'Composição familiar',
                'financiamento_status' => 'Status financiamento',
                'preferencia_tipo_imovel' => 'Tipo imóvel',
                'preferencia_bairro' => 'Bairro',
                'quartos' => 'Quartos',
                'suites' => 'Suítes',
                'garagem' => 'Vagas garagem'
            ];
            
            foreach ($campos_chaves as $campo => $label) {
                if (!empty($lead->$campo)) {
                    echo "  • $label: {$lead->$campo}\n";
                }
            }
        } else {
            echo "✗ Pessoa #{$lead->pessoa_id} não encontrada!\n";
        }
    } else {
        echo "✗ Lead SEM pessoa vinculada\n";
    }
    
    echo "\n" . str_repeat('-', 70) . "\n\n";
}

echo "\n=== RESUMO ===\n";
echo "Total de leads verificados: " . $leads->count() . "\n";

$com_preferencias = $leads->filter(function($lead) {
    if (!$lead->pessoa_id) return false;
    $pessoa = Pessoa::find($lead->pessoa_id);
    if (!$pessoa) return false;
    $prefs = is_string($pessoa->preferencias) 
        ? json_decode($pessoa->preferencias, true) 
        : $pessoa->preferencias;
    return !empty($prefs);
})->count();

$com_interesses = $leads->filter(function($lead) {
    if (!$lead->pessoa_id) return false;
    $pessoa = Pessoa::find($lead->pessoa_id);
    if (!$pessoa) return false;
    $ints = is_string($pessoa->interesses) 
        ? json_decode($pessoa->interesses, true) 
        : $pessoa->interesses;
    return !empty($ints);
})->count();

echo "Com preferências mapeadas: $com_preferencias\n";
echo "Com interesses mapeados: $com_interesses\n";

if ($com_preferencias === 0 && $com_interesses === 0) {
    echo "\n⚠️  ATENÇÃO: Nenhum lead tem preferências/interesses mapeados!\n";
    echo "💡 Execute: php artisan leads:sync-pessoas --force para remapear\n";
} else {
    echo "\n✅ Mapeamento funcionando!\n";
}
