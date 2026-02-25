<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Tenant;

echo "\n=== MOSTRANDO JSON COMPLETO ===\n\n";

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

echo $body;

?>
