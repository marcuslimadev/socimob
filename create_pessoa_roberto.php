<?php

require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Lead;
use App\Models\Pessoa;
use Illuminate\Support\Facades\Log;

// Buscar lead Roberto Jr por telefone
$telefone = '5531971809143'; // ou +5531971809143
$telefones = [$telefone, '+' . $telefone, '55' . substr($telefone, 2)];

echo "Buscando lead Roberto Jr...\n";

$lead = Lead::where(function($q) use ($telefones) {
    foreach ($telefones as $tel) {
        $q->orWhere('telefone', 'LIKE', '%' . $tel . '%')
          ->orWhere('whatsapp', 'LIKE', '%' . $tel . '%')
          ->orWhere('celular', 'LIKE', '%' . $tel . '%');
    }
})->first();

if (!$lead) {
    echo "Lead não encontrado!\n";
    exit;
}

echo "Lead encontrado:\n";
echo "ID: {$lead->id}\n";
echo "Nome: {$lead->nome}\n";
echo "Telefone: {$lead->telefone}\n";
echo "WhatsApp: {$lead->whatsapp}\n";
echo "Email: {$lead->email}\n";
echo "Status: {$lead->status}\n";
echo "Pessoa ID: " . ($lead->pessoa_id ?? 'NULL') . "\n";
echo "Tenant ID: {$lead->tenant_id}\n\n";

if ($lead->pessoa_id) {
    $pessoa = Pessoa::find($lead->pessoa_id);
    if ($pessoa) {
        echo "Pessoa já existe:\n";
        echo "ID: {$pessoa->id}\n";
        echo "Nome: {$pessoa->nome}\n";
        echo "Email: {$pessoa->email}\n";
        echo "Celular: {$pessoa->celular}\n";
        exit;
    } else {
        echo "Lead tem pessoa_id mas pessoa não existe! Vamos criar...\n\n";
    }
}

// Verificar se já existe pessoa com esse telefone ou email
$telefone = $lead->whatsapp ?: $lead->telefone;
$pessoa = null;

if (!empty($telefone)) {
    $pessoa = Pessoa::withoutGlobalScope('tenant')
        ->where('tenant_id', $lead->tenant_id)
        ->where(function($q) use ($telefone) {
            $q->where('telefone', $telefone)
              ->orWhere('celular', $telefone)
              ->orWhere('whatsapp', $telefone);
        })
        ->first();
}

if (!$pessoa && !empty($lead->email)) {
    $pessoa = Pessoa::withoutGlobalScope('tenant')
        ->where('tenant_id', $lead->tenant_id)
        ->where('email', $lead->email)
        ->first();
}

if ($pessoa) {
    echo "Pessoa existente encontrada:\n";
    echo "ID: {$pessoa->id}\n";
    echo "Nome: {$pessoa->nome}\n";
    echo "Email: {$pessoa->email}\n";
    echo "Celular: {$pessoa->celular}\n\n";
    
    // Associar ao lead
    $lead->update(['pessoa_id' => $pessoa->id]);
    echo "Lead associado à pessoa existente!\n";
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
    
    echo "Pessoa criada:\n";
    echo "ID: {$pessoa->id}\n";
    echo "Nome: {$pessoa->nome}\n";
    echo "Email: {$pessoa->email}\n";
    echo "Celular: {$pessoa->celular}\n\n";
    
    // Associar ao lead
    $lead->update(['pessoa_id' => $pessoa->id]);
    echo "Lead associado à nova pessoa!\n";
}

echo "\n✓ Concluído!\n";
