<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __dir__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "=== Criando Pessoas de Teste ===\n\n";
    
    // Buscar tenant
    $tenant = DB::table('tenants')
        ->where('domain', 'exclusivalarimoveis.com')
        ->first();
    
    if (!$tenant) {
        echo "❌ Tenant não encontrado!\n";
        exit(1);
    }
    
    echo "Usando Tenant: {$tenant->name} (ID: {$tenant->id})\n\n";
    
    // Pessoas de teste
    $pessoas = [
        [
            'tenant_id' => $tenant->id,
            'nome' => 'João Silva Santos',
            'tipo' => 'fisica',
            'cpf' => '123.456.789-00',
            'email' => 'joao.silva@email.com',
            'telefone' => '(92) 3234-5678',
            'celular' => '(92) 99234-5678',
            'pais' => 'Brasil',
            'estado' => 'AM',
            'cidade' => 'Manaus',
            'bairro' => 'Centro',
            'endereco' => 'Rua Principal',
            'numero' => '123',
            'cep' => '69000-000',
            'ativo' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ],
        [
            'tenant_id' => $tenant->id,
            'nome' => 'Maria Oliveira Costa',
            'tipo' => 'fisica',
            'cpf' => '987.654.321-00',
            'email' => 'maria.oliveira@email.com',
            'telefone' => '(92) 3234-9876',
            'celular' => '(92) 99876-5432',
            'pais' => 'Brasil',
            'estado' => 'AM',
            'cidade' => 'Manaus',
            'bairro' => 'Adrianópolis',
            'endereco' => 'Avenida Djalma Batista',
            'numero' => '456',
            'cep' => '69050-000',
            'ativo' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ],
        [
            'tenant_id' => $tenant->id,
            'nome' => 'Imobiliária Premium LTDA',
            'tipo' => 'juridica',
            'cnpj' => '12.345.678/0001-90',
            'razao_social' => 'Imobiliária Premium Negócios Imobiliários LTDA',
            'email' => 'contato@premium.com.br',
            'telefone' => '(92) 3234-1111',
            'celular' => '(92) 99111-2222',
            'pais' => 'Brasil',
            'estado' => 'AM',
            'cidade' => 'Manaus',
            'bairro' => 'Vieiralves',
            'endereco' => 'Avenida Efigênio Sales',
            'numero' => '789',
            'cep' => '69060-000',
            'inscricao_estadual' => '123456789',
            'inscricao_municipal' => '987654321',
            'ativo' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ],
        [
            'tenant_id' => $tenant->id,
            'nome' => 'Pedro Henrique Souza',
            'tipo' => 'fisica',
            'cpf' => '456.789.123-00',
            'email' => 'pedro.souza@email.com',
            'celular' => '(92) 99345-6789',
            'pais' => 'Brasil',
            'estado' => 'AM',
            'cidade' => 'Manaus',
            'bairro' => 'Ponta Negra',
            'endereco' => 'Rua do Turismo',
            'numero' => '321',
            'cep' => '69037-000',
            'ativo' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ],
        [
            'tenant_id' => $tenant->id,
            'nome' => 'Ana Carolina Ferreira',
            'tipo' => 'fisica',
            'cpf' => '321.654.987-00',
            'email' => 'ana.ferreira@email.com',
            'telefone' => '(92) 3234-7777',
            'celular' => '(92) 99777-8888',
            'pais' => 'Brasil',
            'estado' => 'AM',
            'cidade' => 'Manaus',
            'bairro' => 'Flores',
            'endereco' => 'Rua das Flores',
            'numero' => '555',
            'cep' => '69058-000',
            'ativo' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ],
    ];
    
    foreach ($pessoas as $pessoa) {
        DB::table('pessoas')->insert($pessoa);
        echo "✓ Criada: {$pessoa['nome']}\n";
    }
    
    $total = count($pessoas);
    echo "\n✅ {$total} pessoas criadas com sucesso!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
