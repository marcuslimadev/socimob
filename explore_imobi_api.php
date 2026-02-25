<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

echo "=== EXPLORANDO ESTRUTURA DA API ===\n\n";

$baseUrl = 'https://imobibrasil.com.br';
$apiKey = env('EXCLUSIVA_API_TOKEN');

// Teste 1: Acessar a raiz sem chave
echo "1. GET $baseUrl (sem autenticação)\n";
try {
    $response = Http::timeout(5)->get($baseUrl);
    echo "   Status: " . $response->status() . "\n";
    $body = $response->body();
    if (strlen($body) < 500) {
        echo "   Body: " . $body . "\n";
    } else {
        // Procurar por palavras-chave
        if (strpos($body, 'api') !== false) echo "   ✓ Contém 'api'\n";
        if (strpos($body, 'documentation') !== false) echo "   ✓ Contém 'documentation'\n";
        if (strpos($body, 'v1') !== false) echo "   ✓ Contém 'v1'\n";
        if (strpos($body, 'properties') !== false) echo "   ✓ Contém 'properties'\n";
    }
} catch (\Exception $e) {
    echo "   Erro: " . substr($e->getMessage(), 0, 100) . "\n";
}

echo "\n2. Testando endpoints comuns\n";

$endpoints = [
    '/api',
    '/api/v1',
    '/api/v1/properties',
    '/properties',
    '/docs',
    '/api/docs',
    '/swagger',
    '/api-docs',
];

foreach ($endpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->timeout(3)->get($url);
        
        echo "   ✅ GET $endpoint -> " . $response->status() . "\n";
    } catch (\Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Could not resolve') !== false) {
            continue; // Pular DNS errors
        }
        if (strpos($msg, '404') !== false) {
            echo "   ❌ GET $endpoint -> 404 Not Found\n";
        } elseif (strpos($msg, '401') !== false || strpos($msg, 'Unauthorized') !== false) {
            echo "   ⚠️  GET $endpoint -> 401 Unauthorized (endpoint existe!)\n";
        } elseif (strpos($msg, '403') !== false) {
            echo "   ⚠️  GET $endpoint -> 403 Forbidden (endpoint existe!)\n";
        }
    }
}

echo "\n3. Tentando com Bearer Token como query parameter\n";
$urls_to_test = [
    $baseUrl . '/api/properties?token=' . urlencode($apiKey),
    $baseUrl . '/properties?token=' . urlencode($apiKey),
];

foreach ($urls_to_test as $url) {
    try {
        $response = Http::timeout(3)->get($url);
        if ($response->status() != 404) {
            echo "   ✅ Endpoint funciona com query param\n";
        }
    } catch (\Exception $e) {
        // Silent fail
    }
}
