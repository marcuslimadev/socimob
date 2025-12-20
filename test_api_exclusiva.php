<?php

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->instance('Illuminate\Contracts\Console\Kernel', $app->make('Laravel\Lumen\Console\Kernel'));

use App\Models\Tenant;
use GuzzleHttp\Client;

// Buscar tenant
$tenant = Tenant::find(1);

if (!$tenant) {
    echo "Tenant não encontrado!\n";
    exit;
}

echo "Tenant: " . $tenant->name . "\n";
echo "API URL: " . $tenant->api_url_externa . "\n";
echo "Token: " . $tenant->api_token_externa . "\n\n";

// Testar API
$client = new Client([
    'verify' => false,
    'timeout' => 30,
    'http_errors' => false
]);

// Montar URL completa
$url = rtrim($tenant->api_url_externa, '/') . '/api/v1/app/imovel/lista';

echo "URL completa: $url\n\n";

try {
    $response = $client->get($url, [
        'headers' => [
            'token' => $tenant->api_token_externa,
            'Content-Type' => 'application/json'
        ],
        'query' => [
            'pagina' => 1,
            'limite' => 3
        ]
    ]);

    $statusCode = $response->getStatusCode();
    $body = $response->getBody()->getContents();

    echo "Status: $statusCode\n";
    echo "Response: " . substr($body, 0, 500) . "\n";

    if ($statusCode === 200) {
        $data = json_decode($body, true);
        if ($data && isset($data['resultSet']['data'])) {
            echo "Imóveis encontrados: " . count($data['resultSet']['data']) . "\n";
            
            // Mostrar primeiro imóvel
            if (!empty($data['resultSet']['data'])) {
                $primeiro = $data['resultSet']['data'][0];
                echo "Primeiro imóvel: " . $primeiro['codigoImovel'] . " - " . ($primeiro['tipoImovel'] ?? 'N/A') . "\n";
            }
        } else {
            echo "Estrutura de dados inesperada\n";
        }
    }

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}