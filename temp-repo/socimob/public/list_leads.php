<?php

/**
 * Script para listar todos os leads do banco de dados
 * Uso: curl https://lojadaesquina.store/list_leads.php -H "X-Admin-Secret: ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8="
 */

// Autenticação
$secretHeader = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
$expectedSecret = 'ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8=';

if (php_sapi_name() !== 'cli' && $secretHeader !== $expectedSecret) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Carregar .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configurar banco
$capsule = new DB;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port'      => $_ENV['DB_PORT'] ?? '3306',
    'database'  => $_ENV['DB_DATABASE'] ?? 'exclusiva',
    'username'  => $_ENV['DB_USERNAME'] ?? 'root',
    'password'  => $_ENV['DB_PASSWORD'] ?? '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

header('Content-Type: application/json');

try {
    // Pegar todas as colunas sem especificar (evitar erros de colunas inexistentes)
    $leads = DB::table('leads')
        ->orderBy('id', 'DESC')
        ->limit(50)
        ->get();

    $total = DB::table('leads')->count();
    $comTenant = DB::table('leads')->whereNotNull('tenant_id')->count();
    $semTenant = DB::table('leads')->whereNull('tenant_id')->count();

    echo json_encode([
        'success' => true,
        'total_leads' => $total,
        'com_tenant' => $comTenant,
        'sem_tenant' => $semTenant,
        'leads' => $leads
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
