<?php
/**
 * Script para limpar cache via browser
 * Acesse: https://lojadaesquina.store/clear_cache.php
 */

// Segurança: requer parâmetro secreto
if (!isset($_GET['secret']) || $_GET['secret'] !== 'limpar123') {
    die('Acesso negado');
}

echo "<h1>Limpeza de Cache - SOCIMOB</h1>";
echo "<pre>";

// Limpar cache do bootstrap
$bootstrapCache = __DIR__ . '/bootstrap/cache';
if (is_dir($bootstrapCache)) {
    $files = glob($bootstrapCache . '/*.php');
    foreach ($files as $file) {
        if (unlink($file)) {
            echo "✓ Removido: " . basename($file) . "\n";
        }
    }
    echo "\n✅ Cache do Bootstrap limpo!\n";
} else {
    echo "⚠️ Diretório bootstrap/cache não encontrado\n";
}

// Limpar OPcache (se disponível)
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache limpo!\n";
} else {
    echo "ℹ️ OPcache não disponível\n";
}

// Verificar arquivo crítico
$controllerPath = __DIR__ . '/app/Http/Controllers/Admin/TenantSettingsController.php';
if (file_exists($controllerPath)) {
    $size = filesize($controllerPath);
    $modified = date('Y-m-d H:i:s', filemtime($controllerPath));
    echo "\n📄 TenantSettingsController.php:\n";
    echo "   Tamanho: " . number_format($size) . " bytes\n";
    echo "   Modificado: $modified\n";
    
    // Verificar se contém Validator::make
    $content = file_get_contents($controllerPath);
    if (strpos($content, 'Validator::make') !== false) {
        echo "   ✅ Contém Validator::make (correção aplicada!)\n";
    } else {
        echo "   ❌ NÃO contém Validator::make (arquivo desatualizado!)\n";
    }
} else {
    echo "❌ Arquivo não encontrado!\n";
}

echo "\n</pre>";
echo "<p><strong>Cache limpo!</strong> Tente fazer upload do logo novamente.</p>";
echo "<p><a href='/app/configuracoes.html'>Voltar para Configurações</a></p>";
?>
