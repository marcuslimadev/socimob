<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Property;
use App\Services\ImobiBrasilService;
use App\Models\Tenant;

echo "\n=== DESCOBRINDO O ENDPOINT CORRETO DE IMAGENS ===\n\n";

$tenant = Tenant::where('name', 'Exclusiva Lar Imoveis')->first();
$apiKey = $tenant->getIntegrationValue('api_token_externa') ?: env('EXCLUSIVA_API_TOKEN');
$baseUrl = 'https://exclusivalarimoveis.com.br/api/v1/app';

// Pegar uma propriedade que foi enviada com sucesso
$property = Property::where('id', 457)->first(); // Aquela que funcionou no teste anterior
if (!$property) {
    echo "❌ Propriedade 457 não encontrada\n";
    exit;
}

echo "Propriedade: ID=" . $property->id . ", Externa=" . $property->imobi_brasil_external_id . "\n";
echo "Código: " . $property->codigo . "\n\n";

$client = new \GuzzleHttp\Client([
    'verify' => false,
    'timeout' => 30,
]);

// Preparar o payload corretamente (igual ao que funciona)
$payload = ImobiBrasilService::class;  //  Vou chamar o método preparePropertyPayload

// Na verdade, vou usar o método da classe
$fullPayload = [
    'finalidade' => 'venda',
    'referencia' => $property->referencia ?? ('PROP-' . $property->id),
    'codigoTipoImovel' => 1,
    'descricaoImovel' => $property->descricao ?? 'Teste',
    'codigoProprietario' => 1,
    'codigoCorretor' => 1,
    'portaisDivulgarConvencional' => ['sim'],
    'CEP' => $property->cep ?? '30140071',
    'bairro' => $property->bairro ?? 'Centro',
    'logradouro' => $property->logradouro ?? 'Rua Teste',
    'numero' => $property->numero ?? '0',
];

// Testar ATUALIZAR uma propriedade já enviada COM FOTOS no payload
$testPayloads = [
    [
        'name' => 'Without images',
        'payload' => $fullPayload,
    ],
    [
        'name' => 'With fotos array',
        'payload' => array_merge($fullPayload, [
            'fotos' => [
                'https://via.placeholder.com/600x400?text=Foto+1',
                'https://via.placeholder.com/600x400?text=Foto+2',
            ]
        ]),
    ],
    [
        'name' => 'With imagens array',
        'payload' => array_merge($fullPayload, [
            'imagens' => [
                'https://via.placeholder.com/600x400?text=Imagem+1',
                'https://via.placeholder.com/600x400?text=Imagem+2',
            ]
        ]),
    ],
];

$codigoImovel = $property->imobi_brasil_external_id;

foreach ($testPayloads as $test) {
    echo "=== Test: {$test['name']} ===\n";
    echo "Enviando para: PUT /imovel/alterar/" . $codigoImovel . "\n\n";
    
    try {
        $response = $client->post($baseUrl . '/imovel/alterar/' . $codigoImovel, [
            'headers' => [
                'token' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $test['payload'],
            'http_errors' => false,
        ]);
        
        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();
        
        echo "Status: $statusCode\n";
        
        $responseData = json_decode($body, true);
        if (isset($responseData['status'])) {
            echo "Response status: " . ($responseData['status'] ? 'true' : 'false') . "\n";
            if (isset($responseData['message'])) {
                echo "Message: " . $responseData['message'] . "\n";
            }
        } else {
            echo "Raw Response: " . substr($body, 0, 150) . "\n";
        }
        
        if ($statusCode === 200 && ($responseData['status'] ?? false)) {
            echo "✅ Success! Images field " . ($test['name'] === 'Without images' ? 'not included' : 'ACCEPTED') . "\n";
        }
    } catch (\Exception $e) {
        echo "Erro: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Agora testar se pode haver um endpoint separado baseado no padrão de API restful common
echo "\n\n=== TESTANDO ENDPOINTS ALTERNATIVOS PARA GALERIA ===\n\n";

$altEndpoints = [
    '/galeria/inserir',
    '/galeria/adicionar',
    '/fotos/inserir',
    '/fotos/adicionar',
    '/imovel/galeria/adicionar',
    '/galeria',
];

foreach ($altEndpoints as $endpoint) {
    try {
        $response = $client->post($baseUrl . $endpoint, [
            'headers' => [
                'token' => $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'codigoImovel' => $codigoImovel,
                'url' => 'https://via.placeholder.com/600x400?text=Test',
                'destaque' => false,
            ],
            'http_errors' => false,
        ]);
        
        $statusCode = $response->getStatusCode();
        
        // Mostrar apenas se não for 404
        if ($statusCode !== 404) {
            echo "POST $endpoint => Status $statusCode\n";
            if ($statusCode < 300) {
                echo "  ✅ Possível endpoint de galeria!\n";
            }
        }
    } catch (\Exception $e) {
        // Ignorar
    }
}

?>
