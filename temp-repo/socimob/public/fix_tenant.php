<?php

/**
 * Atribuir tenant_id = 1 para todos os leads sem tenant
 */

$secretHeader = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
if ($secretHeader !== 'ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8=') {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$capsule = new DB;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_DATABASE'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

header('Content-Type: application/json');

try {
    // Atualizar leads sem tenant
    $updated = DB::table('leads')
        ->whereNull('tenant_id')
        ->update(['tenant_id' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

    // Atualizar conversas sem tenant
    $conversasUpdated = DB::table('conversas')
        ->whereNull('tenant_id')
        ->update(['tenant_id' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

    echo json_encode([
        'success' => true,
        'leads_atualizados' => $updated,
        'conversas_atualizadas' => $conversasUpdated
    ]);

} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
