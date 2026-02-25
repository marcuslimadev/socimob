<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

echo "=== IDENTIFICANDO URL CORRETA DA API ===\n\n";

$apiKey = env('EXCLUSIVA_API_TOKEN');

// Testar URLs prováveis
$possibleUrls = [
    'https://imobibrasil.com.br',
    'https://imobibrasil.com',
    'https://app.imobibrasil.com.br',
];

foreach ($possibleUrls as $baseUrl) {
    echo "Testando: $baseUrl\n";
    
    $endpoints = [
        '/api/v1/properties',
        '/api/properties',
        '/v1/properties',
        '/properties',
        '/api',
    ];
    
    foreach ($endpoints as $endpoint) {
        $url = $baseUrl . $endpoint;
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(3)
            ->get($url);
            
            $status = $response->status();
            if ($status == 200 || $status == 401 || $status == 403) {
                // 401/403 significa que a URL existe mas a autenticação falhou
                echo "  ✅ $endpoint -> Status $status\n";
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'Connection refused') !== false || 
                strpos($msg, '404') !== false ||
                strpos($msg, '403') !== false ||
                strpos($msg, '401') !== false) {
                // Esses erros significam que a URL existe
                echo "  ✅ $endpoint -> Endpoint existe\n";
            }
        }
    }
    echo "\n";
}

// Testar GET na raiz dos domínios
echo "--- Teste de GET nas raízes ---\n";
foreach ($possibleUrls as $url) {
    try {
        $response = Http::timeout(3)->get($url);
        echo "✅ $url -> " . $response->status() . "\n";
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), 'Could not resolve') === false) {
            echo "⚠️  $url -> Endpoint existe mas erro de autenticação\n";
        }
    }
}
