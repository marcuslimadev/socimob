<?php
/**
 * Script para atualizar mascot_url da Exclusiva diretamente no banco
 * Não depende de Composer/Laravel
 */

// Carregar .env manualmente
function loadEnv($path) {
    if (!file_exists($path)) {
        die("Arquivo .env não encontrado em $path\n");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

try {
    $pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "=== ATUALIZANDO MASCOT DA EXCLUSIVA ===\n\n";

    // Update Exclusiva tenant
    $stmt = $pdo->prepare("UPDATE tenants SET mascot_url = '/assets/turtle.png', updated_at = NOW() WHERE slug = 'exclusiva' OR domain LIKE '%exclusiva%'");
    $stmt->execute();
    
    $affected = $stmt->rowCount();
    echo "✅ Mascot URL atualizado! ($affected tenant(s) afetado(s))\n";

    // Verify
    $stmt = $pdo->prepare("SELECT id, name, domain, mascot_url FROM tenants WHERE slug = 'exclusiva' OR domain LIKE '%exclusiva%' LIMIT 1");
    $stmt->execute();
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tenant) {
        echo "\nTenant atualizado:\n";
        echo "  ID: {$tenant['id']}\n";
        echo "  Nome: {$tenant['name']}\n";
        echo "  Domain: {$tenant['domain']}\n";
        echo "  Mascot URL: " . ($tenant['mascot_url'] ?? 'NULL') . "\n";
    }

    echo "\n✅ Atualização concluída!\n";

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
