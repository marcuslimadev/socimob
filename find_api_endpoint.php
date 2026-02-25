<?php
require_once 'bootstrap/app.php';

use App\Models\Tenant;

$tenant = Tenant::find(1);
$apiUrl = $tenant->getIntegrationValue('api_url_externa');
$apiToken = $tenant->getIntegrationValue('api_token_externa');
$baseUrl = rtrim($apiUrl, '/');

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  PROCURAR ENDPOINT CORRETO PARA POST (não HTML)                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$testPayload = json_encode([
    'titulo' => 'TEST',
    'cidade' => 'BH',
    'tipo_imovel' => 'apartamento'
]);

$endpoints = [
    '/api/imovel',
    '/api/imovel/create',
    '/api/imovel/store',
    '/api/properties',
    '/api/properties/create',
    '/api/create-property',
    '/imovel/api',
    '/imovel/api/create',
    '/api/property-create',
    '/v1/imovel',
    '/v1/properties',
];

$client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 10, 'http_errors' => false]);

echo "Testando endpoints:\n\n";

foreach ($endpoints as $path) {
    $url = $baseUrl . $path;
    
    $response = $client->post($url, [
        'headers' => [
            'token' => $apiToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'json' => ['titulo' => 'TEST', 'tipo_imovel' => 'apto'],
    ]);
    
    $status = $response->getStatusCode();
    $contentType = $response->getHeaderLine('Content-Type');
    $body = $response->getBody()->getContents();
    
    $isJson = strpos($contentType, 'json') !== false || strpos($body, '{') === 0;
    $isHtml = strpos($contentType, 'html') !== false || strpos($body, '<!') === 0;
    
    $symbol = '   ';
    if ($isJson && $status < 400) $symbol = '✅';
    elseif ($isJson) $symbol = '🟡';
    elseif ($isHtml) $symbol = '❌';
    elseif ($status === 404) $symbol = '404';
    
    printf("%s  %-30s  HTTP %d  %s\n", $symbol, $path, $status, substr($contentType, 0, 20));
    
    if ($isJson && ($status === 200 || $status === 201)) {
        $data = json_decode($body, true);
        echo "    → JSON Response:\n";
        echo "      " . json_encode($data, JSON_UNESCAPED_SLASHES) . "\n";
    }
}

echo "\n✅ = Endpoint JSON com sucesso\n";
echo "🟡 = Endpoint JSON mas erro\n";
echo "❌ = Retorna HTML\n";
echo "404 = Não existe\n";
