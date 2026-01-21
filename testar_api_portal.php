<?php

echo "Testando API do portal...\n\n";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Content-Type: application/json'
    ]
]);

$response = file_get_contents('http://127.0.0.1:8000/api/portal/imoveis', false, $context);

if ($response === false) {
    echo "❌ Erro ao acessar a API\n";
    exit(1);
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Erro ao decodificar JSON: " . json_last_error_msg() . "\n";
    exit(1);
}

echo "✅ API funcionando!\n";
echo "Total de imóveis retornados: " . count($data) . "\n\n";

if (count($data) > 0) {
    echo "Primeiros 3 imóveis:\n";
    for ($i = 0; $i < min(3, count($data)); $i++) {
        $imovel = $data[$i];
        echo "- {$imovel['titulo']} (ID: {$imovel['id']})\n";
    }
} else {
    echo "Nenhum imóvel retornado\n";
}