<?php

require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Lead;
use App\Models\Pessoa;

echo "=== VERIFICAR ROBERTO JR ===\n\n";

// Buscar lead por telefone
$lead = Lead::where('telefone', 'LIKE', '%5531971809143%')
    ->orWhere('whatsapp', 'LIKE', '%5531971809143%')
    ->orWhere('celular', 'LIKE', '%5531971809143%')
    ->first();

if (!$lead) {
    echo "❌ Lead não encontrado!\n";
    exit(1);
}

echo "✓ Lead encontrado:\n";
echo "  ID: {$lead->id}\n";
echo "  Nome: {$lead->nome}\n";
echo "  Status: {$lead->status}\n";
echo "  Telefone: {$lead->telefone}\n";
echo "  WhatsApp: {$lead->whatsapp}\n";
echo "  Email: {$lead->email}\n";
echo "  Pessoa ID: " . ($lead->pessoa_id ?? 'NULL') . "\n\n";

if ($lead->pessoa_id) {
    $pessoa = Pessoa::withoutGlobalScope('tenant')->find($lead->pessoa_id);
    if ($pessoa) {
        echo "✓ Pessoa associada:\n";
        echo "  ID: {$pessoa->id}\n";
        echo "  Nome: {$pessoa->nome}\n";
        echo "  Email: {$pessoa->email}\n";
        echo "  Celular: {$pessoa->celular}\n\n";
        echo "✅ TUDO OK - Pessoa já existe!\n";
    } else {
        echo "❌ ERRO: Lead tem pessoa_id={$lead->pessoa_id} mas pessoa não existe!\n";
        echo "Criando pessoa agora...\n\n";
        goto criar_pessoa;
    }
} else {
    echo "⚠️ Lead SEM pessoa associada. Criando agora...\n\n";
    goto criar_pessoa;
}

exit(0);

criar_pessoa:

// Verificar se já existe pessoa com esse telefone
$telefone = $lead->whatsapp ?: $lead->telefone;
$pessoa = Pessoa::withoutGlobalScope('tenant')
    ->where('tenant_id', $lead->tenant_id)
    ->where(function($q) use ($telefone) {
        $q->where('telefone', $telefone)
          ->orWhere('celular', $telefone)
          ->orWhere('whatsapp', $telefone);
    })
    ->first();

if ($pessoa) {
    echo "✓ Pessoa existente encontrada: {$pessoa->nome} (ID: {$pessoa->id})\n";
    $lead->update(['pessoa_id' => $pessoa->id]);
    echo "✓ Lead associado à pessoa existente!\n";
} else {
    echo "Criando nova pessoa...\n";
    $pessoa = Pessoa::create([
        'tenant_id' => $lead->tenant_id,
        'nome' => $lead->nome,
        'email' => $lead->email,
        'telefone' => $lead->telefone,
        'celular' => $lead->whatsapp ?: $lead->telefone,
        'whatsapp' => $lead->whatsapp,
        'cpf' => $lead->cpf ?? null,
        'tipo' => 'fisica',
        'pais' => 'Brasil',
        'ativo' => true,
        'papeis' => ['cliente', 'lead'],
        'status' => $lead->status ?? 'ativo',
        'origem' => 'Lead CRM',
        'corretor_responsavel_id' => $lead->corretor_id ?? null,
        'renda_mensal' => $lead->renda_mensal ?? null,
        'profissao' => $lead->profissao ?? null,
        'observacoes' => $lead->observacoes,
        'ultimo_contato' => $lead->ultima_interacao ?? now(),
        'primeiro_contato' => $lead->primeira_interacao ?? $lead->created_at,
    ]);
    
    echo "✓ Pessoa criada: {$pessoa->nome} (ID: {$pessoa->id})\n";
    
    $lead->update(['pessoa_id' => $pessoa->id]);
    echo "✓ Lead associado à nova pessoa!\n";
}

echo "\n✅ CONCLUÍDO!\n";
echo "Acesse: https://lojadaesquina.store/pessoas/{$pessoa->id}\n";
