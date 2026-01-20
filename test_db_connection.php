<?php

require_once __DIR__.'/vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(
    dirname(__DIR__)
))->bootstrap();

$app = require_once __DIR__.'/bootstrap/app.php';

try {
    $pdo = $app->make('db')->connection()->getPdo();
    echo "✅ Conexão com banco de dados OK\n";

    // Testar se podemos fazer uma query simples
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Query de teste OK: " . $result['test'] . "\n";

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}