<?php

require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Lead;
use App\Models\Pessoa;
use Illuminate\Support\Facades\Schema;

echo "=== ANÁLISE DE MAPEAMENTO CHAVES NA MÃO → PESSOA ===\n\n";

echo "--- CAMPOS DA TABELA LEADS ---\n";
$leadColumns = Schema::getColumnListing('leads');
sort($leadColumns);
foreach ($leadColumns as $col) {
    echo "  - {$col}\n";
}

echo "\n--- CAMPOS DA TABELA PESSOAS ---\n";
$pessoaColumns = Schema::getColumnListing('pessoas');
sort($pessoaColumns);
foreach ($pessoaColumns as $col) {
    echo "  - {$col}\n";
}

echo "\n--- CAMPOS DO LEAD QUE NÃO ESTÃO SENDO MAPEADOS ---\n";

// Campos importantes do Lead que existem
$camposImportantes = [

    'nome',
    'email',
    'telefone',
    'whatsapp',
    'cpf',
    'renda_mensal',
    'profissao',
    'estado_civil',
    'composicao_familiar',
    'fonte_renda',
    'financiamento_status',
    'prazo_compra',
    'objetivo_compra',
    'preferencia_tipo_imovel',
    'preferencia_bairro',
    'preferencia_lazer',
    'preferencia_seguranca',
    'caracteristicas_desejadas',
    'observacoes_cliente',
    'observacoes',
    'budget_min',
    'budget_max',
    'localizacao',
    'corretor_id',
];

// Verificar quais existem na tabela leads
echo "\nCampos do Lead que existem:\n";
foreach ($camposImportantes as $campo) {
    if (in_array($campo, $leadColumns)) {
        echo "  ✓ {$campo}\n";
    } else {
        echo "  ✗ {$campo} (NÃO EXISTE NA TABELA)\n";
    }
}

// Pegar um lead do Chaves na Mão para exemplo
echo "\n--- EXEMPLO DE LEAD DO CHAVES NA MÃO ---\n";
$lead = Lead::where('observacoes', 'LIKE', '%Chaves na%')->first();

if ($lead) {
    echo "Lead ID: {$lead->id}\n";
    echo "Nome: {$lead->nome}\n\n";
    
    echo "Campos preenchidos:\n";
    foreach ($camposImportantes as $campo) {
        if (property_exists($lead, $campo) && !empty($lead->$campo)) {
            $valor = is_array($lead->$campo) ? json_encode($lead->$campo) : $lead->$campo;
            if (strlen($valor) > 100) {
                $valor = substr($valor, 0, 100) . '...';
            }
            echo "  - {$campo}: {$valor}\n";
        }
    }
    
    // Verificar se tem pessoa associada
    if ($lead->pessoa_id) {
        echo "\n--- PESSOA ASSOCIADA ---\n";
        $pessoa = Pessoa::find($lead->pessoa_id);
        if ($pessoa) {
            echo "Pessoa ID: {$pessoa->id}\n";
            echo "Nome: {$pessoa->nome}\n";
            echo "Campos preenchidos na pessoa:\n";
            foreach (['email', 'telefone', 'celular', 'whatsapp', 'cpf', 'renda_mensal', 'profissao', 'observacoes'] as $campo) {
                if (property_exists($pessoa, $campo) && !empty($pessoa->$campo)) {
                    echo "  - {$campo}: {$pessoa->$campo}\n";
                }
            }
        }
    }
} else {
    echo "Nenhum lead do Chaves na Mão encontrado.\n";
}

echo "\n=== FIM DA ANÁLISE ===\n";
