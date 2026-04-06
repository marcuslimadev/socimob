<?php
try {
    require 'vendor/autoload.php';
    $app = require_once 'bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
    $contrato = \App\Models\ContratoCompraVenda::with(['imovel', 'vendedor', 'comprador'])->find(1);
    if(!$contrato) { echo 'Contrato 1 nao existe'; exit; }
    $service = app(\App\Services\ContratoCompraVendaDocumentoService::class);
    $service->gerarPdf($contrato, 'compra_venda', null);
    echo 'OK';
} catch (\Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . ' no arquivo ' . $e->getFile() . ' linha ' . $e->getLine();
}
