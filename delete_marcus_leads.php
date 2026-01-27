<?php

require_once __DIR__.'/bootstrap/app.php';

use App\Models\Lead;
use App\Models\Conversa;

$app->boot();

// Buscar leads com o telefone do Marcus
$phone = '31973127682';
$leads = Lead::where('telefone', 'like', "%{$phone}%")
    ->orWhere('whatsapp', 'like', "%{$phone}%")
    ->get();

echo "Encontrados: " . $leads->count() . " leads com o número {$phone}\n\n";

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
