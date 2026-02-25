<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Tenant;

echo "\n=== VERIFICANDO ESTRUTURA REAL DO JSON ===\n\n";

$tenant = Tenant::where('name', 'Exclusiva Lar Imoveis')->first();
$apiKey = $tenant->getIntegrationValue('api_token_externa') ?: env('EXCLUSIVA_API_TOKEN');
$baseUrl = 'https://exclusivalarimoveis.com.br/api/v1/app';

$client = new \GuzzleHttp\Client([
    'verify' => false,
    'timeout' => 30,
    'http_errors' => false,
]);

$response = $client->get($baseUrl . '/imovel/lista', [
    'headers' => [
        'token' => $apiKey,
        'Accept' => 'application/json',
    ],
    'query' => [
        'page' => 1,
        'limit' => 10,
    ],
]);

$body = $response->getBody()->getContents();
$data = json_decode($body, true);

// Mostrar as primeiras chaves e primeiros dados
echo "Chaves principais: " . implode(', ', array_keys($data)) . "\n\n";

if (isset($data['resultSet']) && count($data['resultSet']) > 0) {
    echo "Primeira estrutura de imóvel:\n";
    echo json_encode($data['resultSet'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "Total de imóveis: " . count($data['resultSet']) . "\n";
}

?>
