<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Tenant;
use App\Services\ImobiBrasilService;

$tenant = Tenant::where('name', 'Exclusiva Lar Imoveis')->first();
$apiKey = ImobiBrasilService::getApiKey($tenant);
$baseUrl = ImobiBrasilService::getBaseUrl($tenant);
$client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

// Test with high limit
$r = $client->get($baseUrl . '/cidade/lista', [
    'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
    'query' => ['limit' => 1000, 'page' => 1],
]);
$data = json_decode($r->getBody()->getContents(), true);
echo "limit=1000: total_pages={$data['resultSet']['total_pages']}, per_page={$data['resultSet']['per_page']}, total={$data['resultSet']['total_items']}\n\n";

// Now search through pages for Santa Luzia, MG
echo "=== BUSCANDO Santa Luzia - MG ===\n";
$found = null;
$totalPages = $data['resultSet']['total_pages'];
$perPage = $data['resultSet']['per_page'];

// Search in first batch
$allCities = $data['resultSet']['data'];
foreach ($allCities as $city) {
    if (stripos($city['nomeCidade'], 'Santa Luzia') !== false && $city['siglaEstado'] === 'MG') {
        $found = $city;
        echo "ENCONTRADA na página 1: " . json_encode($city) . "\n";
    }
}

if (!$found) {
    echo "Não encontrada na página 1, buscando nas demais ($totalPages páginas total)...\n";
    for ($page = 2; $page <= min($totalPages, 50); $page++) {
        $r = $client->get($baseUrl . '/cidade/lista', [
            'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
            'query' => ['limit' => $perPage, 'page' => $page],
        ]);
        $d = json_decode($r->getBody()->getContents(), true);
        foreach ($d['resultSet']['data'] as $city) {
            if (stripos($city['nomeCidade'], 'Santa Luzia') !== false && $city['siglaEstado'] === 'MG') {
                $found = $city;
                echo "ENCONTRADA na página $page: " . json_encode($city, JSON_UNESCAPED_UNICODE) . "\n";
                break 2;
            }
        }
    }
}

if (!$found) {
    echo "Santa Luzia - MG não encontrada nas primeiras 50 páginas\n";
}
