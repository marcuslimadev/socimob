<?php

// Teste da importação via Exclusiva usando GET

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

echo "🚀 Testando importação via GET da Exclusiva...\n\n";

$baseUrl = 'https://www.exclusivalarimoveis.com.br';
$token = '$2y$10$Lcn1ct.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O';

try {
    $client = new Client([
        'verify' => false,
        'timeout' => 30,
        'http_errors' => false
    ]);

    // Buscar lista de imóveis
    echo "📋 Buscando lista de imóveis...\n";
    
    $response = $client->get($baseUrl . '/api/v1/app/imovel/lista', [
        'headers' => [
            'token' => $token,
            'Content-Type' => 'application/json'
        ],
        'query' => [
            'pagina' => 1,
            'limite' => 3
        ]
    ]);

    if ($response->getStatusCode() === 200) {
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        
        if ($data['status'] && isset($data['resultSet']['data'])) {
            $imoveis = $data['resultSet']['data'];
            echo "✅ Encontrados " . count($imoveis) . " imóveis!\n\n";
            
            // Processar primeiro imóvel
            $primeiro = $imoveis[0];
            echo "🏠 Processando imóvel: " . $primeiro['codigoImovel'] . "\n";
            
            // Buscar detalhes
            $urlDetalhes = $baseUrl . '/api/v1/app/imovel/dados/' . $primeiro['codigoImovel'];
            
            $responseDetalhes = $client->get($urlDetalhes, [
                'headers' => [
                    'token' => $token,
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            if ($responseDetalhes->getStatusCode() === 200) {
                $bodyDetalhes = $responseDetalhes->getBody()->getContents();
                $dataDetalhes = json_decode($bodyDetalhes, true);
                
                if ($dataDetalhes['status'] && isset($dataDetalhes['resultSet'])) {
                    $imovel = $dataDetalhes['resultSet'];
                    
                    echo "✅ Detalhes obtidos!\n";
                    
                    // Simular mapeamento
                    $mapeado = [
                        'codigo_externo' => $imovel['codigoImovel'],
                        'referencia' => $imovel['referenciaImovel'] ?? null,
                        'titulo' => $imovel['descricaoTipoImovel'] ?? 'Imóvel',
                        'tipo' => $imovel['descricaoTipoImovel'] ?? 'Apartamento',
                        'finalidade' => $imovel['finalidadeImovel'] ?? 'Venda',
                        'valor' => $imovel['valorEsperado'] ?? 0,
                        'dormitorios' => $imovel['dormitorios'] ?? 0,
                        'banheiros' => $imovel['banheiros'] ?? 0,
                        'garagem' => $imovel['garagem'] ?? 0,
                        'area_total' => $imovel['areaTotal'] ?? null,
                        'area_construida' => $imovel['areaConstruida'] ?? null,
                        'descricao' => $imovel['observacoes'] ?? '',
                        'endereco' => [
                            'rua' => $imovel['endereco']['rua'] ?? '',
                            'numero' => $imovel['endereco']['numero'] ?? '',
                            'bairro' => $imovel['endereco']['bairro'] ?? '',
                            'cidade' => $imovel['endereco']['cidade'] ?? '',
                            'estado' => $imovel['endereco']['estado'] ?? '',
                            'cep' => $imovel['endereco']['cep'] ?? ''
                        ]
                    ];
                    
                    echo "\n📊 Imóvel mapeado:\n";
                    echo "- Código: " . $mapeado['codigo_externo'] . "\n";
                    echo "- Título: " . $mapeado['titulo'] . "\n";
                    echo "- Tipo: " . $mapeado['tipo'] . "\n";
                    echo "- Finalidade: " . $mapeado['finalidade'] . "\n";
                    echo "- Valor: R$ " . number_format($mapeado['valor'], 2, ',', '.') . "\n";
                    echo "- Quartos: " . $mapeado['dormitorios'] . "\n";
                    echo "- Banheiros: " . $mapeado['banheiros'] . "\n";
                    echo "- Endereço: " . $mapeado['endereco']['rua'] . ", " . $mapeado['endereco']['bairro'] . ", " . $mapeado['endereco']['cidade'] . "\n";
                    
                    $fotos = isset($imovel['imagens']) ? count($imovel['imagens']) : 0;
                    echo "- Fotos disponíveis: " . $fotos . "\n";
                    
                    echo "\n✅ Mapeamento bem-sucedido!\n";
                    echo "🎯 A integração está funcionando corretamente!\n";
                }
            }
        }
    }

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n✓ Teste de importação finalizado\n";