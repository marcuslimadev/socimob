<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Tenant;

echo "\n=== DESCOBRINDO ENDPOINTS DA API IMOBI BRASIL ===\n\n";

$tenant = Tenant::where('name', 'Exclusiva Lar Imoveis')->first();
$apiKey = $tenant->getIntegrationValue('api_token_externa') ?: env('EXCLUSIVA_API_TOKEN');
$baseUrl = 'https://exclusivalarimoveis.com.br/api/v1/app';

$client = new \GuzzleHttp\Client([
    'verify' => false,
    'timeout' => 30,
]);

// Tentar listar todos os endpoints disponíveis
$possibleRoots = [
    '/documentacao',
    '/docs',
    '/api-docs',
    '/swagger',
    '/endpoints',
    '/help',
    '/guia',
];

echo "Tentando endpoints de documentação:\n\n";

foreach ($possibleRoots as $path) {
    try {
        $response = $client->get($baseUrl . $path, [
            'headers' => [
                'token' => $apiKey,
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ]);
        
        $statusCode = $response->getStatusCode();
        if ($statusCode < 404) {
            echo "✅ Found: $path (Status: $statusCode)\n";
            $body = (string)$response->getBody();
            if ($statusCode >= 200 && $statusCode < 300) {
                echo "   " . substr($body, 0, 200) . "\n";
            }
        }
    } catch (\Exception $e) {
        // Ignorar
    }
}

echo "\n\nTentando endpoints conhecidos da Imobi Brasil:\n\n";

// Tentar endpoints comuns para imagens/galeria
$endpoints = [
    'GET /imovel/lista',       // Já funciona
    'GET /galeria',
    'GET /galeria/lista',
    'GET /fotos',
    'POST /galeria/adicionar',
    'POST /imovel/galeria',
    'POST /imovel/adicionar-fotos',
    'POST /imovel/adicionar-imagens',
    'POST /galeria/foto',
    'POST /galeria/imagem',
    'DELETE /galeria/{id}',
    'GET /configuracoes/endpoints',  // Tentar descobrir se há documentação
    'GET /v1/endpoints',
];

foreach ($endpoints as $req) {
    [$method, $path] = explode(' ', $req);
    
    $path = str_replace('{id}', '4154505', $path);
    $url = $baseUrl . $path;
    
    try {
        $options = [
            'headers' => [
                'token' => $apiKey,
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ];
        
        if ($method === 'GET') {
            $response = $client->get($url, $options);
        } else {
            $response = $client->request($method, $url, array_merge($options, [
                'json' => ['url' => 'https://example.com/image.jpg'],
            ]));
        }
        
        $statusCode = $response->getStatusCode();
        
        // Mostrar se houver algo que não seja 404/405
        if ($statusCode !== 404 && $statusCode !== 405 && $statusCode !== 500) {
            echo "$method $path => Status $statusCode ✅\n";
            if ($statusCode >= 200 && $statusCode < 300) {
                $body = (string)$response->getBody();
                echo "   Response: " . substr($body, 0, 100) . "\n";
            }
        }
    } catch (\Exception $e) {
        // Ignorar
    }
}

echo "\n\nTentando listar com query parameters para descobrir filtros/funcionalidades:\n\n";

$params_tests = [
    '/imovel/lista?buscar=galeria',
    '/imovel/lista?tipo=galeria',
    '/imovel/lista?incluir=fotos',
    '/imovel/lista?campos=fotos',
];

foreach ($params_tests as $path) {
    try {
        $response = $client->get($baseUrl . $path, [
            'headers' => [
                'token' => $apiKey,
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ]);
        
        $statusCode = $response->getStatusCode();
        if ($statusCode < 300) {
            echo "✅ GET $path (Status: $statusCode)\n";
        }
    } catch (\Exception $e) {
        // Ignorar
    }
}

?>
