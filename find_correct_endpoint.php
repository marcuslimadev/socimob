<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\Tenant;

echo "=== DESCOBRIR ENDPOINT CORRETO IMOBI BRASIL ===\n\n";

$tenant = Tenant::find(1);
$baseUrl = 'https://exclusivalarimoveis.com.br';
$apiKey = env('EXCLUSIVA_API_TOKEN');

echo "Base: $baseUrl\n";
echo "Token: " . substr($apiKey, 0, 20) . "...\n\n";

// Testar endpoints prováveis para POST (criar/enviar propriedade)
$postEndpoints = [
    '/properties',
    '/imoveis',
    '/api/properties',
    '/api/imoveis',
    '/api/v1/properties',
    '/api/v1/imoveis',
];

echo "--- Testar POST (Enviar Propriedade) ---\n";

$testPayload = [
    'titulo' => 'Teste Imobi Brasil',
    'tipo' => 'apartamento',
    'valor' => 150000,
];

foreach ($postEndpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    
    try {
        $response = Http::withHeaders([
            'token' => $apiKey,
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])
        ->timeout(5)
        ->post($url, $testPayload);
        
        $status = $response->status();
        
        if ($status === 404) {
            //echo "❌ POST $endpoint -> 404 Not Found\n";
        } else if ($status === 401 || $status === 403) {
            echo "⚠️  POST $endpoint -> Status $status (Endpoint existe!)\n";
        } else if ($status === 200 || $status === 201) {
            echo "✅ POST $endpoint -> Status $status (FUNCIONA!)\n";
        } else {
            echo "⚠️  POST $endpoint -> Status $status\n";
        }
    } catch (\Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, '404') !== false) {
            // Skip 404
        } else if (strpos($msg, '401') !== false || strpos($msg, '403') !== false) {
            echo "⚠️  POST $endpoint -> Endpoint existe (auth needed)\n";
        }
    }
}

echo "\n--- Testar GET (Listar Propriedades) ---\n";

$getEndpoints = [
    '/properties',
    '/imoveis',
    '/api/properties',
    '/api/imoveis',
    '/api/v1/properties',
    '/api/v1/imoveis',
];

foreach ($getEndpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    
    try {
        $response = Http::withHeaders([
            'token' => $apiKey,
            'Authorization' => 'Bearer ' . $apiKey,
        ])
        ->timeout(5)
        ->get($url);
        
        $status = $response->status();
        
        if ($status === 404) {
            // Skip 404
        } else if ($status === 401 || $status === 403) {
            echo "⚠️  GET $endpoint -> Status $status (Endpoint existe!)\n";
        } else if ($status === 200) {
            echo "✅ GET $endpoint -> Status $status (FUNCIONA!)\n";
        } else {
            echo "⚠️  GET $endpoint -> Status $status\n";
        }
    } catch (\Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, '404') !== false) {
            // Skip 404
        } else if (strpos($msg, '401') !== false || strpos($msg, '403') !== false) {
            echo "⚠️  GET $endpoint -> Endpoint existe (auth needed)\n";
        }
    }
}

echo "\n--- Resumo ---\n";
echo "Endpoints que retornam 401/403 EXISTEM e precisam de autenticação correta\n";
echo "Todos os endpoints parecem retornar 404, indicando que a estrutura de URL pode estar diferente\n";
echo "\nConsulte a documentação em: https://ajuda.imobibrasil.com.br/assets/documentacao_api_v1/#/\n";
