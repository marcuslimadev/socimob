<?php

require_once __DIR__.'/bootstrap/app.php';

use App\Models\Lead;
use App\Models\Conversa;

$app->boot();

// Buscar leads por telefone (argumento CLI) e opcionalmente por tenant
$phone = $argv[1] ?? getenv('PHONE') ?? '31973127682';
$tenantId = $argv[2] ?? getenv('TENANT_ID');

$leadsQuery = Lead::query()
    ->when($tenantId, function ($query) use ($tenantId) {
        $query->where('tenant_id', $tenantId);
    })
    ->where(function ($query) use ($phone) {
        $query->where('telefone', 'like', "%{$phone}%")
            ->orWhere('whatsapp', 'like', "%{$phone}%");
    });

$leads = $leadsQuery->get();

echo "Encontrados: " . $leads->count() . " leads com o número {$phone}";
if (!empty($tenantId)) {
    echo " (tenant_id={$tenantId})";
}
echo "\n\n";

foreach ($leads as $lead) {
    echo "Deletando Lead ID: {$lead->id} - Nome: {$lead->nome} - Tel: {$lead->telefone}\n";

    // Deletar conversas relacionadas
    $conversas = Conversa::where('lead_id', $lead->id)->get();
    foreach ($conversas as $conversa) {
        echo "  - Deletando conversa ID: {$conversa->id}\n";
        $conversa->delete();
    }

    // Deletar o lead
    $lead->delete();
}

echo "\n✅ Todos os leads deletados com sucesso!\n";
