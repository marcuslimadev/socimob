<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Tenant;

echo "\n=== VERIFICANDO SE FOTOS ESTÃO INCLUÍDAS NA RESPOSTA DO /imovel/lista ===\n\n";

$tenant = Tenant::where('name', 'Exclusiva Lar Imoveis')->first();
$apiKey = $tenant->getIntegrationValue('api_token_externa') ?: env('EXCLUSIVA_API_TOKEN');
$baseUrl = 'https://exclusivalarimoveis.com.br/api/v1/app';

$client = new \GuzzleHttp\Client([
    'verify' => false,
    'timeout' => 30,
]);

// Buscar com diferentes parâmetros
$queries = [
    '/imovel/lista?limit=5',
    '/imovel/lista?limit=5&incluir=fotos',
    '/imovel/lista?limit=5&campos=fotos',
    '/imovel/lista?limit=5&campos=*',
];

foreach ($queries as $path) {
    echo "=== GET $path ===\n";
    
    try {
        $response = $client->get($baseUrl . $path, [
            'headers' => [
                'token' => $apiKey,
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ]);
        
        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();
        
        echo "Status: $statusCode\n";
        
        $data = json_decode($body, true);
        
        if (isset($data['resultSet']['data']) && !empty($data['resultSet']['data'])) {
            $firstItem = $data['resultSet']['data'][0];
            
            echo "Primeiro item tem campos:\n";
            foreach (array_keys($firstItem) as $field) {
                echo "  - " . $field . "\n";
            }
            
            // Se houver fotos, mostrar
            if (isset($firstItem['fotos'])) {
                echo "\n✅ Campo 'fotos' encontrado!\n";
                echo "Tipo: " . gettype($firstItem['fotos']) . "\n";
                echo "Conteúdo: " . json_encode($firstItem['fotos'], JSON_UNESCAPED_UNICODE) . "\n";
            } else if (isset($firstItem['imagens'])) {
                echo "\n✅ Campo 'imagens' encontrado!\n";
                echo "Tipo: " . gettype($firstItem['imagens']) . "\n";
                echo "Conteúdo: " . json_encode($firstItem['imagens'], JSON_UNESCAPED_UNICODE) . "\n";
            } else if (isset($firstItem['galeria'])) {
                echo "\n✅ Campo 'galeria' encontrado!\n";
                echo "Tipo: " . gettype($firstItem['galeria']) . "\n";
                echo "Conteúdo: " . json_encode($firstItem['galeria'], JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "\n❌ Nenhum campo de fotos encontrado\n";
            }
        }
    } catch (\Exception $e) {
        echo "Erro: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Agora testar se o payload do imovel na criação pode incluir fotos
echo "\n\n=== TESTANDO ENVIO DE IMAGENS NO PAYLOAD DO IMOVEL ===\n\n";

$testPayload = [
    'finalidade' => 'venda',
    'referencia' => 'TEST-IMAGES-' . time(),
    'codigoTipoImovel' => 1,
    'descricaoImovel' => 'Teste de Envio de Imagens',
    'codigoProprietario' => 1,
    'codigoCorretor' => 1,
    'portaisDivulgarConvencional' => ['sim'],
    'CEP' => '30140071',
    'bairro' => 'Centro',
    'logradouro' => 'Avenida Getúlio Vargas',
    'numero' => '100',
    
    // Testar diferentes formatos para enviar fotos
    'fotos' => [
        'https://via.placeholder.com/600x400?text=Foto+1',
        'https://via.placeholder.com/600x400?text=Foto+2',
    ],
];

echo "Testando payload com campo 'fotos':\n";

try {
    $response = $client->post($baseUrl . '/imovel/inserir', [
        'headers' => [
            'token' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'json' => $testPayload,
        'http_errors' => false,
    ]);
    
    $statusCode = $response->getStatusCode();
    $body = (string)$response->getBody();
    
    echo "Status: $statusCode\n";
    echo "Response: " . json_encode(json_decode($body, true), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

// Testar com campo 'imagens'
$testPayload['imagens'] = $testPayload['fotos'];
unset($testPayload['fotos']);

echo "\n\nTestando payload com campo 'imagens':\n";

try {
    $response = $client->post($baseUrl . '/imovel/inserir', [
        'headers' => [
            'token' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'json' => $testPayload,
        'http_errors' => false,
    ]);
    
    $statusCode = $response->getStatusCode();
    $body = (string)$response->getBody();
    
    echo "Status: $statusCode\n";
    echo "Response: " . json_encode(json_decode($body, true), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

// Testar com campo 'galeria'
$testPayload['galeria'] = $testPayload['imagens'];
unset($testPayload['imagens']);

echo "\n\nTestando payload com campo 'galeria':\n";

try {
    $response = $client->post($baseUrl . '/imovel/inserir', [
        'headers' => [
            'token' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'json' => $testPayload,
        'http_errors' => false,
    ]);
    
    $statusCode = $response->getStatusCode();
    $body = (string)$response->getBody();
    
    echo "Status: $statusCode\n";
    echo "Response: " . json_encode(json_decode($body, true), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

?>
