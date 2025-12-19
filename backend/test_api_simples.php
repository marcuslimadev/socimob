<?php

// Teste simples da API Exclusiva

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

echo "🚀 Testando API Exclusiva diretamente...\n\n";

$baseUrl = 'https://www.exclusivalarimoveis.com.br';
$token = '$2y$10$Lcn1ct.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O';

try {
    $client = new Client([
        'verify' => false,
        'timeout' => 30,
        'http_errors' => false
    ]);

    // Teste 1: Lista de imóveis
    echo "📋 Testando lista de imóveis...\n";
    $url = $baseUrl . '/api/v1/app/imoveis/lista';
    
    $response = $client->post($url, [
        'headers' => [
            'token' => $token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'pagina' => 1,
            'limite' => 3
        ])
    ]);

    echo "Status: " . $response->getStatusCode() . "\n";
    $body = $response->getBody()->getContents();
    
    if ($response->getStatusCode() === 200) {
        $data = json_decode($body, true);
        
        echo "✅ Sucesso!\n";
        echo "Status da API: " . ($data['status'] ? 'true' : 'false') . "\n";
        
        if (isset($data['resultSet']['data'])) {
            $imoveis = $data['resultSet']['data'];
            echo "Imóveis encontrados: " . count($imoveis) . "\n\n";
            
            // Mostrar primeiro imóvel
            if (!empty($imoveis)) {
                $primeiro = $imoveis[0];
                echo "Primeiro imóvel:\n";
                echo "- Código: " . $primeiro['codigoImovel'] . "\n";
                echo "- Referência: " . $primeiro['referenciaImovel'] . "\n";
                echo "- Status: " . ($primeiro['statusImovel'] ? 'Ativo' : 'Inativo') . "\n\n";
                
                // Teste 2: Detalhes do primeiro imóvel
                echo "🔍 Buscando detalhes do imóvel " . $primeiro['codigoImovel'] . "...\n";
                
                $urlDetalhes = $baseUrl . '/api/v1/app/imoveis/dados/' . $primeiro['codigoImovel'];
                
                $responseDetalhes = $client->get($urlDetalhes, [
                    'headers' => [
                        'token' => $token,
                        'Content-Type' => 'application/json'
                    ]
                ]);
                
                echo "Status: " . $responseDetalhes->getStatusCode() . "\n";
                
                if ($responseDetalhes->getStatusCode() === 200) {
                    $bodyDetalhes = $responseDetalhes->getBody()->getContents();
                    $dataDetalhes = json_decode($bodyDetalhes, true);
                    
                    if ($dataDetalhes['status'] && isset($dataDetalhes['resultSet'])) {
                        $imovel = $dataDetalhes['resultSet'];
                        
                        echo "✅ Detalhes obtidos!\n";
                        echo "- Tipo: " . ($imovel['descricaoTipoImovel'] ?? 'N/A') . "\n";
                        echo "- Finalidade: " . ($imovel['finalidadeImovel'] ?? 'N/A') . "\n";
                        echo "- Valor: R$ " . number_format($imovel['valorEsperado'] ?? 0, 2, ',', '.') . "\n";
                        echo "- Quartos: " . ($imovel['dormitorios'] ?? 0) . "\n";
                        echo "- Banheiros: " . ($imovel['banheiros'] ?? 0) . "\n";
                        echo "- Garagem: " . ($imovel['garagem'] ?? 0) . "\n";
                        echo "- Cidade: " . ($imovel['endereco']['cidade'] ?? 'N/A') . "\n";
                        echo "- Bairro: " . ($imovel['endereco']['bairro'] ?? 'N/A') . "\n";
                        echo "- Fotos: " . (isset($imovel['imagens']) ? count($imovel['imagens']) : 0) . "\n";
                    } else {
                        echo "❌ Erro: " . json_encode($dataDetalhes) . "\n";
                    }
                } else {
                    echo "❌ Erro HTTP: " . $responseDetalhes->getStatusCode() . "\n";
                    echo "Response: " . $responseDetalhes->getBody()->getContents() . "\n";
                }
            }
        } else {
            echo "❌ Estrutura de dados inesperada\n";
            echo "Response: " . substr($body, 0, 500) . "...\n";
        }
    } else {
        echo "❌ Erro HTTP: " . $response->getStatusCode() . "\n";
        echo "Response: " . $body . "\n";
    }

} catch (Exception $e) {
    echo "❌ Erro na requisição: " . $e->getMessage() . "\n";
}

echo "\n✓ Teste finalizado\n";