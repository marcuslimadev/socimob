<?php

// Teste da importação via endpoint HTTP

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

echo "🌐 Testando importação via endpoint HTTP...\n\n";

try {
    $client = new Client([
        'verify' => false,
        'timeout' => 60, // Mais tempo para importação
        'http_errors' => false
    ]);

    // Token de um usuário válido (pegar do localStorage ou gerar novo)
    $token = 'base64token'; // Substituir por token real

    echo "📞 Chamando endpoint de importação...\n";
    
    $response = $client->post('http://127.0.0.1:8000/api/admin/imoveis/importar', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ],
        'json' => [
            'fonte' => 'exclusiva'
        ]
    ]);

    echo "Status: " . $response->getStatusCode() . "\n";
    $body = $response->getBody()->getContents();
    
    if ($response->getStatusCode() === 200) {
        $data = json_decode($body, true);
        echo "✅ Resposta:\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ Erro:\n";
        echo $body . "\n";
    }

} catch (Exception $e) {
    echo "❌ Erro na requisição: " . $e->getMessage() . "\n";
}

echo "\n✓ Teste finalizado\n";