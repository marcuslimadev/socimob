<?php

// Teste direto da importação de imóveis - sem autenticação

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap/app.php';

use App\Http\Controllers\Admin\ImportacaoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Configurar variáveis de ambiente para o banco
if (!getenv('DB_HOST')) {
    putenv('DB_HOST=127.0.0.1');
    putenv('DB_DATABASE=exclusiva');
    putenv('DB_USERNAME=root');
    putenv('DB_PASSWORD=');
}

// Configurar a conexão do banco
$app = require __DIR__ . '/bootstrap/app.php';
$app->withEloquent();

echo "🏠 Testando importação direta de imóveis...\n\n";

try {
    // Simular dados do tenant (Exclusiva)
    $tenantData = (object) [
        'id' => 1,
        'api_url_externa' => 'https://www.exclusivalarimoveis.com.br',
        'api_token_externa' => '$2y$10$Lcn1ct.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O'
    ];

    // Criar request simulado
    $request = new Request();
    $request->merge([
        'fonte' => 'exclusiva'
    ]);

    echo "📋 Configuração:\n";
    echo "- API URL: " . $tenantData->api_url_externa . "\n";
    echo "- Token: " . substr($tenantData->api_token_externa, 0, 20) . "...\n";
    echo "- Fonte: exclusiva\n\n";

    echo "🚀 Iniciando importação...\n";

    // Instanciar controller
    $controller = new ImportacaoController();
    
    // Usar reflection para acessar método privado
    $reflection = new ReflectionClass($controller);
    
    // Testar método buscarImoveisExternos
    $method = $reflection->getMethod('buscarImoveisExternos');
    $method->setAccessible(true);
    
    echo "📡 Chamando API externa...\n";
    $imoveis = $method->invoke($controller, 
        $tenantData->api_url_externa, 
        $tenantData->api_token_externa, 
        'exclusiva'
    );

    if (empty($imoveis)) {
        echo "❌ Nenhum imóvel encontrado!\n";
        return;
    }

    echo "✅ " . count($imoveis) . " imóveis encontrados!\n\n";

    // Mostrar primeiros imóveis
    foreach (array_slice($imoveis, 0, 3) as $i => $imovel) {
        echo "🏠 Imóvel " . ($i + 1) . ":\n";
        echo "- Código: " . ($imovel['codigo_externo'] ?? 'N/A') . "\n";
        echo "- Título: " . ($imovel['titulo'] ?? 'N/A') . "\n";
        echo "- Tipo: " . ($imovel['tipo'] ?? 'N/A') . "\n";
        echo "- Valor: R$ " . number_format($imovel['valor'] ?? 0, 2, ',', '.') . "\n";
        echo "- Quartos: " . ($imovel['dormitorios'] ?? 0) . "\n";
        echo "- Cidade: " . ($imovel['endereco']['cidade'] ?? 'N/A') . "\n";
        echo "\n";
    }

    // Testar método de processamento
    echo "💾 Testando processamento dos dados...\n";
    $processMethod = $reflection->getMethod('processarImportacao');
    $processMethod->setAccessible(true);
    
    $resultado = $processMethod->invoke($controller, $imoveis, $tenantData->id);

    echo "📊 Resultado do processamento:\n";
    echo "- Importados: " . $resultado['importados'] . "\n";
    echo "- Duplicados: " . $resultado['duplicados'] . "\n";
    echo "- Erros: " . $resultado['erros'] . "\n";
    echo "- Total processados: " . count($imoveis) . "\n";

    if ($resultado['importados'] > 0) {
        echo "\n🎉 Importação concluída com sucesso!\n";
    } else {
        echo "\n⚠️ Nenhum imóvel foi importado.\n";
    }

} catch (Exception $e) {
    echo "❌ Erro durante teste: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n✓ Teste finalizado\n";