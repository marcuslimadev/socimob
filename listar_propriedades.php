<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Property;

echo "Propriedades disponíveis (primeiras 10):\n";
$props = Property::limit(10)->get(['id', 'titulo', 'imobi_brasil_sent', 'imobi_brasil_external_id']);

foreach ($props as $p) {
    echo "ID=" . $p->id . " | Enviada=" . ($p->imobi_brasil_sent ? 'SIM' : 'NÃO') . " | External=" . ($p->imobi_brasil_external_id ?? 'null') . " | Título: " . substr($p->titulo, 0, 40) . "\n";
}

// Encontrar uma que não foi enviada
echo "\n\nBuscando propriedade não enviada...\n";
$notSent = Property::where('imobi_brasil_sent', '!=', true)->limit(1)->first();

if ($notSent) {
    echo "✅ Encontrada: ID=" . $notSent->id . "\n";
} else {
    // Se todas foram, limpávar uma
    echo "Todas foram enviadas. Resetando ID 2 para teste...\n";
    $p = Property::find(2);
    if ($p) {
        $p->update([
            'imobi_brasil_sent' => false,
            'imobi_brasil_external_id' => null,
        ]);
        echo "✅ ID=2 resetada\n";
    }
}

?>
