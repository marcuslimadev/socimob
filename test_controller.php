<?php

require __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$app = require __DIR__ . '/bootstrap/app.php';
$app->boot();

use Illuminate\Http\Request;
use App\Http\Controllers\Portal\PortalController;

echo "Testando controller diretamente...\n\n";

$request = Request::create('/api/portal/imoveis', 'GET');
$request->attributes->set('tenant_id', 1);

$controller = new PortalController();
$response = $controller->getImoveis($request);

$data = json_decode($response->getContent(), true);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Total retornado: " . count($data['data']) . "\n";

if (count($data['data']) > 0) {
    echo "\nPrimeiro imóvel:\n";
    $imovel = $data['data'][0];
    echo "- ID: " . ($imovel['id'] ?? 'NULL') . "\n";
    echo "- Título: " . ($imovel['titulo'] ?? 'NULL') . "\n";
    echo "- Cidade: " . ($imovel['cidade'] ?? 'NULL') . "\n";
} else {
    echo "Nenhum imóvel retornado\n";
}