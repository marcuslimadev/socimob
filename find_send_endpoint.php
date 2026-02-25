<?php
require_once 'bootstrap/app.php';

$tenant = \App\Models\Tenant::find(1);
$apiUrl = $tenant->getIntegrationValue('api_url_externa'); // https://exclusivalarimoveis.com.br
$apiToken = $tenant->getIntegrationValue('api_token_externa'); // EXCLUSIVA_API_TOKEN

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║    DESCOBRIR ENDPOINT CORRETO PARA ENVIAR IMÓVEL                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "URL base: $apiUrl\n";
echo "Token: " . substr($apiToken, 0, 20) . "...\n\n";

$endpoints = [
    '/api/imovel/inserir',
    '/api/imovel/create',
    '/api/properties',
    '/api/properties/create',
    '/imovel',
    '/imovel/create',
    '/imovel/inserir',
    '/imovel/novo',
    '/properties',
    '/propriedades',
    '/api/v1/propriedades',
];

$payload = [
    'titulo' => 'TEST - ' . date('Y-m-d H:i:s'),
    'tipo_imovel' => 'apartamento',
    'finalidade_imovel' => 'venda',
    'valor_venda' => 100000,
    'endereco' => 'Rua Teste, 123',
    'logradouro' => 'Rua Teste',
    'numero' => '123',
    'bairro' => 'Centro',
    'cidade' => 'Belo Horizonte',
    'estado' => 'MG',
    'dormitorios' => 2,
    'banheiros' => 1,
];

echo "Testando endpoints para POST:\n";

foreach ($endpoints as $endpoint) {
    $url = $apiUrl . $endpoint;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'token: ' . $apiToken,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $status = '❓';
    if ($httpCode >= 200 && $httpCode < 300) {
        $status = '✅';
    } elseif ($httpCode == 404) {
        $status = '404';
    } elseif ($httpCode == 401 || $httpCode == 403) {
        $status = '🔐';
    } elseif ($httpCode >= 500) {
        $status = '❌';
    }
    
    printf("%s  %-35s → HTTP %3d\n", $status, $endpoint, $httpCode);
    
    // Se encontout sucesso, mostrar resposta
    if ($httpCode >= 200 && $httpCode < 300) {
        echo "    Resposta: " . substr($response, 0, 150) . "\n\n";
    }
}

echo "\n ✅ = Sucesso (200-299)\n";
echo " 🔐 = Autenticação falhou (401/403)\n";
echo " ❌ = Erro servidor (500+)\n";
echo " 404 = Endpoint não existe\n";
