<?php

require_once __DIR__.'/bootstrap/app.php';

use App\Models\Lead;

$app->boot();

$leadIds = [96, 97, 98];

foreach ($leadIds as $id) {
    $lead = Lead::find($id);
    if ($lead) {
        echo "Lead {$id}: {$lead->nome} - Tel: {$lead->telefone} - WhatsApp: {$lead->whatsapp}\n";
    }
}
