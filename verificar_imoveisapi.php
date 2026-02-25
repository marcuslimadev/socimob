<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Tenant;

echo "\n=== VERIFICANDO IMÓVEIS NA API IMOBI BRASIL ===\n\n";

$tenant = Tenant::where('name', 'Exclusiva Lar Imoveis')->first();
$apiKey = $tenant->getIntegrationValue('api_token_externa') ?: env('EXCLUSIVA_API_TOKEN');
$baseUrl = 'https://exclusivalarimoveis.com.br/api/v1/app';

echo "Tenant: {$tenant->name}\n";
echo "API Key: " . substr($apiKey, 0, 10) . "...\n";
echo "Base URL: $baseUrl\n\n";

// Consultar lista de imóveis
echo "=== LISTANDO IMÓVEIS DA API ===\n";

$client = new \GuzzleHttp\Client([
    'verify' => false,
    'timeout' => 30,
    'http_errors' => false,
]);

try {
    $response = $client->get($baseUrl . '/imovel/lista', [
        'headers' => [
            'token' => $apiKey,
            'Accept' => 'application/json',
        ],
        'query' => [
            'page' => 1,
            'limit' => 50,
        ],
    ]);

    $statusCode = $response->getStatusCode();
    $body = $response->getBody()->getContents();

    echo "HTTP Status: $statusCode\n";
    echo "Response Length: " . strlen($body) . " bytes\n\n";

    $data = json_decode($body, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        echo "✅ Resposta é JSON\n\n";

        // Procurar por informações de contagem
        if (isset($data['total_items'])) {
            echo "Total de imóveis: {$data['total_items']}\n";
        } elseif (isset($data['total_pages'])) {
            echo "Total de páginas: {$data['total_pages']}\n";
        }

        if (isset($data['data']) && is_array($data['data'])) {
            echo "Imóveis na resposta: " . count($data['data']) . "\n\n";
            
            // Mostrar os últimos 3 imóveis
            echo "Últimos imóveis:\n";
            $imoveisMostrados = array_slice($data['data'], -3);
            
            foreach ($imoveisMostrados as $imovel) {
                echo "---\n";
                echo "ID/Código: " . ($imovel['codigo'] ?? $imovel['codigoImovel'] ?? $imovel['id'] ?? 'N/A') . "\n";
                echo "Título: " . ($imovel['descricaoImovel'] ?? $imovel['titulo'] ?? 'N/A') . "\n";
                echo "Referência: " . ($imovel['referencia'] ?? 'N/A') . "\n";
                echo "Valor: R$ " . ($imovel['valorImovel'] ?? $imovel['valor_venda'] ?? 'N/A') . "\n";
                echo "Data Inserção: " . ($imovel['dataInsercaoImovel'] ?? $imovel['created_at'] ?? 'N/A') . "\n";
            }
            echo "---\n\n";
        } elseif (isset($data['resultSet']) && is_array($data['resultSet'])) {
            echo "Imóveis no resultSet: " . count($data['resultSet']) . "\n\n";
            
            echo "Últimos imóveis:\n";
            $imoveisMostrados = array_slice($data['resultSet'], -3);
            
            foreach ($imoveisMostrados as $imovel) {
                echo "---\n";
                echo "ID/Código: " . ($imovel['codigo'] ?? $imovel['codigoImovel'] ?? $imovel['id'] ?? 'N/A') . "\n";
                echo "Título: " . ($imovel['descricaoImovel'] ?? $imovel['titulo'] ?? 'N/A') . "\n";
                echo "Referência: " . ($imovel['referencia'] ?? 'N/A') . "\n";
                echo "Valor: R$ " . ($imovel['valorImovel'] ?? $imovel['valor_venda'] ?? 'N/A') . "\n";
            }
            echo "---\n\n";
        } else {
            echo "Chaves no response: " . implode(', ', array_keys($data)) . "\n";
            echo "Primeiro nível do JSON:\n";
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    } else {
        echo "❌ Resposta não é JSON\n";
        echo "Conteúdo: " . substr($body, 0, 500) . "\n";
    }

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";
?>
