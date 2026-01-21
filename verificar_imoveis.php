<?php

require __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$app = require __DIR__ . '/bootstrap/app.php';
$app->boot();

use App\Models\Property;

echo "Total de imóveis: " . Property::count() . PHP_EOL;
echo "Imóveis do tenant 1: " . Property::where('tenant_id', 1)->count() . PHP_EOL;

// Mostrar alguns imóveis recentes
$recentes = Property::where('tenant_id', 1)->orderBy('created_at', 'desc')->limit(5)->get();
echo PHP_EOL . "Últimos 5 imóveis:" . PHP_EOL;
foreach ($recentes as $imovel) {
    echo "- ID: {$imovel->id}, Título: {$imovel->titulo}, Status: {$imovel->status}" . PHP_EOL;
}