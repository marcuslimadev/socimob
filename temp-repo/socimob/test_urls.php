<?php

// Teste exploratório da API Exclusiva - identificar URLs corretas

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

echo "🔍 Explorando API Exclusiva para identificar URLs corretas...\n\n";

$baseUrl = 'https://www.exclusivalarimoveis.com.br';
$token = '$2y$10$Lcn1ct.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O';

$client = new Client([
    'verify' => false,
    'timeout' => 30,
    'http_errors' => false
]);

// URLs possíveis para testar
$urlsParaTestar = [
    '/api/v1/app/imovel/lista',
    '/api/v1/app/imoveis/lista',
    '/api/v1/imovel/lista',
    '/api/v1/imoveis/lista',
    '/api/app/imovel/lista',
    '/api/app/imoveis/lista',
    '/api/imovel/lista',
    '/api/imoveis/lista',
    '/app/api/imovel/lista',
    '/app/api/imoveis/lista'
];

foreach ($urlsParaTestar as $endpoint) {
    echo "🧪 Testando: " . $baseUrl . $endpoint . "\n";
    
    try {
        $response = $client->post($baseUrl . $endpoint, [
            'headers' => [
                'token' => $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'pagina' => 1,
                'limite' => 1
            ])
        ]);

        $status = $response->getStatusCode();
        echo "Status: $status ";
        
        if ($status === 200) {
            echo "✅ SUCESSO!\n";
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);
            
            if (isset($data['status']) && $data['status']) {
                echo "Resposta válida da API!\n";
                if (isset($data['resultSet']['data'])) {
                    echo "Dados encontrados: " . count($data['resultSet']['data']) . " imóveis\n";
                    
                    // Se encontrou dados, mostrar um imóvel como exemplo
                    if (!empty($data['resultSet']['data'])) {
                        $exemplo = $data['resultSet']['data'][0];
                        echo "Exemplo - Código: " . ($exemplo['codigoImovel'] ?? 'N/A') . "\n";
                    }
                }
            } else {
                echo "API respondeu mas status = false\n";
            }
        } elseif ($status === 404) {
            echo "❌ Não encontrada\n";
        } else {
            echo "⚠️ Status: $status\n";
            $body = $response->getBody()->getContents();
            if (strlen($body) < 200) {
                echo "Response: $body\n";
            }
        }
        
    } catch (Exception $e) {
        echo "💥 Erro: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "\n📋 Resumo dos testes concluído!\n";