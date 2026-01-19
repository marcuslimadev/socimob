<?php

// Script de teste da importação da API Exclusiva

require __DIR__ . '/vendor/autoload.php';

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$app = require __DIR__ . '/bootstrap/app.php';

// Configurar banco
$app->instance('db', $app['db']);

use App\Http\Controllers\Admin\ImportacaoController;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\User;

// Criar request fake com autenticação
$tenant = Tenant::find(1);
$user = User::where('tenant_id', 1)->first();

if (!$user || !$tenant) {
    die("❌ Erro: Tenant ou User não encontrado\n");
}

// Criar controller
$controller = new ImportacaoController();

// Criar request fake
$request = new Illuminate\Http\Request();
$request->merge([
    'fonte' => 'exclusiva'
]);

// Injetar user no request
$request->setUserResolver(function() use ($user) {
    return $user;
});

echo "🚀 Iniciando teste de importação...\n\n";
echo "Tenant: {$tenant->name}\n";
echo "User: {$user->name}\n";
echo "API URL: {$tenant->api_url_externa}\n";
echo "Token: " . substr($tenant->api_token_externa, 0, 30) . "...\n\n";

try {
    $response = $controller->importar($request);
    $data = json_decode($response->getContent(), true);
    
    echo "✅ Importação concluída!\n\n";
    echo "Resultados:\n";
    echo "- Importados: " . ($data['importados'] ?? 0) . "\n";
    echo "- Duplicados: " . ($data['duplicados'] ?? 0) . "\n";
    echo "- Erros: " . ($data['erros'] ?? 0) . "\n";
    echo "- Total: " . ($data['total'] ?? 0) . "\n";
    
    if (isset($data['error'])) {
        echo "\n❌ Erro: " . $data['error'] . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Erro na importação: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n✓ Teste finalizado\n";
