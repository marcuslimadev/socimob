<?php

// Script para importar imóveis reais da API Exclusiva usando credenciais de produção

require __DIR__ . '/vendor/autoload.php';

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$app = require __DIR__ . '/bootstrap/app.php';

// Inicializar a aplicação
$app->boot();

use App\Http\Controllers\Admin\ImportacaoController;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "🚀 Iniciando importação de imóveis reais...\n\n";

// Verificar conexão com banco de produção
try {
    $pdo = new PDO(
        "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_DATABASE'] . ";port=" . $_ENV['DB_PORT'],
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexão com banco de produção estabelecida\n";
} catch (Exception $e) {
    die("❌ Erro na conexão com banco: " . $e->getMessage() . "\n");
}

// Buscar tenant Exclusiva
$tenant = Tenant::find(1);
if (!$tenant) {
    die("❌ Erro: Tenant Exclusiva não encontrado\n");
}

echo "🏢 Tenant: {$tenant->name}\n";
echo "🌐 Domínio: {$tenant->domain}\n";

// Buscar usuário do tenant
$user = User::where('tenant_id', 1)->where('role', 'admin')->first();
if (!$user) {
    $user = User::where('tenant_id', 1)->first();
}

if (!$user) {
    die("❌ Erro: Nenhum usuário encontrado para o tenant\n");
}

echo "👤 Usuário: {$user->name} ({$user->email})\n";

// Verificar configurações da API
$apiToken = $_ENV['EXCLUSIVA_API_TOKEN'] ?? null;
if (!$apiToken) {
    die("❌ Erro: Token da API Exclusiva não configurado\n");
}

echo "🔑 Token da API: " . substr($apiToken, 0, 20) . "...\n";

// Criar controller
$controller = new ImportacaoController();

// Criar request fake
$request = new Illuminate\Http\Request();
$request->merge([
    'fonte' => 'exclusiva',
    'periodoInicial' => date('Y-m-d', strtotime('-30 days')),
    'periodoFinal' => date('Y-m-d'),
    'incluirDetalhes' => true,
    'processarMidia' => true,
    'atualizarExistentes' => true
]);

// Injetar user no request
$request->setUserResolver(function() use ($user) {
    return $user;
});

echo "\n📅 Período: " . $request->periodoInicial . " até " . $request->periodoFinal . "\n";
echo "⚙️  Configurações: Detalhes=Sim, Mídia=Sim, Atualizar=Sim\n\n";

try {
    echo "🔄 Executando importação...\n";
    $response = $controller->importarSincrono($request);

    // Aguardar um pouco para o processamento
    sleep(2);

    // Buscar resultados do job mais recente
    $job = DB::table('import_jobs')
        ->where('tipo', 'api_exclusiva_sincrono')
        ->orderBy('created_at', 'desc')
        ->first();

    if ($job) {
        $parametros = json_decode($job->parametros ?? '{}', true);
        $resultado = $parametros['resultado'] ?? [];

        echo "✅ Importação concluída!\n\n";
        echo "📊 Resultados:\n";
        echo "- Status: " . $job->status . "\n";
        echo "- Novos imóveis: " . ($resultado['importados'] ?? 0) . "\n";
        echo "- Duplicados: " . ($resultado['duplicados'] ?? 0) . "\n";
        echo "- Erros: " . ($resultado['erros'] ?? 0) . "\n";
        echo "- Total processado: " . ($job->processados ?? 0) . "\n";
        echo "- Tempo de execução: " . ($job->tempo_execucao ?? 0) . " segundos\n";
    } else {
        echo "⚠️  Importação executada, mas não foi possível obter os resultados detalhados.\n";
    }

} catch (\Exception $e) {
    echo "❌ Erro na importação: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n✓ Processo finalizado\n";