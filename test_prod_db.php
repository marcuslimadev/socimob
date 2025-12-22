<?php
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';

try {
    $db = $app->make('db');
    $users = $db->table('users')->count();
    echo "✅ Banco conectado! Total de usuários: {$users}\n";
    
    $tenants = $db->table('tenants')->count();
    echo "✅ Total de tenants: {$tenants}\n";
    
    $properties = $db->table('properties')->count();
    echo "✅ Total de imóveis: {$properties}\n";
    
} catch (Exception $e) {
    echo "❌ Erro: {$e->getMessage()}\n";
}
