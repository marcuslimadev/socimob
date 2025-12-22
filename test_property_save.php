<?php

require_once __DIR__.'/vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(
    dirname(__DIR__)
))->bootstrap();

$app = require_once __DIR__.'/bootstrap/app.php';

use App\Models\Property;

try {
    echo "🧪 Testando salvamento de Property...\n\n";
    
    $data = [
        'tenant_id' => 1,
        'codigo' => 'TEST123',
        'titulo' => 'Teste de Imóvel',
        'tipo_imovel' => 'casa',
        'finalidade_imovel' => 'venda',
        'valor_venda' => 500000.00,
        'dormitorios' => 3,
        'banheiros' => 2,
        'garagem' => 2,
        'logradouro' => 'Rua Teste, 123',
        'cidade' => 'Belo Horizonte',
        'estado' => 'MG',
        'bairro' => 'Centro',
        'area_total' => 150.00,
        'descricao' => 'Imóvel de teste',
        'imagens' => ['https://example.com/img1.jpg', 'https://example.com/img2.jpg'],
        'active' => true,
        'exibir_imovel' => true,
        'external_id' => 'EXT_TEST_123',
        'last_sync' => date('Y-m-d H:i:s')
    ];
    
    echo "📋 Dados:\n";
    print_r($data);
    echo "\n";
    
    $property = Property::create($data);
    
    echo "✅ Property criado com sucesso!\n";
    echo "ID: {$property->id}\n";
    echo "Código: {$property->codigo}\n";
    echo "Título: {$property->titulo}\n";
    echo "Valor: R$ " . number_format($property->valor_venda, 2, ',', '.') . "\n";
    echo "Dormitórios: {$property->dormitorios}\n";
    echo "Imagens: " . (is_array($property->imagens) ? count($property->imagens) : 'N/A') . "\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
