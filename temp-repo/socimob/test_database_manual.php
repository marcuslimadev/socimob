<?php

// Script para testar importação com configuração manual da database

// Importar configurações
require_once __DIR__ . '/vendor/autoload.php';

// Configurar conexão manual
$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'exclusiva',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// Agora testar o modelo
use App\Models\Property;
use App\Models\Tenant;

echo "Testando conexão...\n";

try {
    $tenant = Tenant::find(1);
    if ($tenant) {
        echo "Tenant encontrado: " . $tenant->name . "\n";
        
        // Testar busca de propriedades
        $properties = Property::where('tenant_id', $tenant->id)->count();
        echo "Propriedades existentes: $properties\n";
        
        // Testar criação de uma propriedade de teste
        $test = Property::create([
            'tenant_id' => $tenant->id,
            'codigo' => 'TEST_' . time(),
            'external_id' => 'test_' . time(),
            'titulo' => 'Teste de Importação',
            'descricao' => 'Propriedade de teste',
            'finalidade_imovel' => 'Venda',
            'tipo_imovel' => 'Casa',
            'preco' => 350000.00,
            'cidade' => 'Teste',
            'estado' => 'TE',
            'endereco' => 'Rua de Teste, 123, Centro',
            'active' => true,
            'exibir_imovel' => true
        ]);
        
        echo "Propriedade de teste criada com ID: " . $test->id . "\n";
        
        // Remover a propriedade de teste
        $test->delete();
        echo "Propriedade de teste removida.\n";
        
    } else {
        echo "Tenant não encontrado!\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}