<?php
/**
 * Script de Deploy via HTTP
 * Acesse: https://lojadaesquina.store/deploy_queue.php?key=exclusiva2025
 */

// Segurança: chave secreta
$secretKey = 'exclusiva2025';
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die('Acesso negado');
}

header('Content-Type: text/plain; charset=utf-8');

echo "🚀 DEPLOY DO SISTEMA DE FILA\n";
echo str_repeat("=", 60) . "\n\n";

// Diretório do projeto
$projectPath = __DIR__;
chdir($projectPath);

// 1. Git Pull
echo "📦 Fazendo git pull...\n";
$output = [];
exec('git pull origin master 2>&1', $output, $returnCode);
echo implode("\n", $output) . "\n";

if ($returnCode !== 0) {
    echo "\n❌ Erro ao fazer git pull (code: $returnCode)\n";
} else {
    echo "\n✅ Git pull concluído\n";
}

echo "\n";

// 2. Verificar arquivos modificados
echo "📁 Arquivos do sistema de fila:\n";
$files = [
    'app/Http/Controllers/Admin/ConversasController.php',
    'public/app/chat.html',
    'routes/web.php'
];

foreach ($files as $file) {
    $fullPath = $projectPath . '/' . $file;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        $modified = date('Y-m-d H:i:s', filemtime($fullPath));
        echo "  ✅ $file ($size bytes, modificado em $modified)\n";
    } else {
        echo "  ❌ $file NÃO ENCONTRADO\n";
    }
}

echo "\n";

// 3. Limpar OPcache
echo "🔄 Limpando OPcache...\n";
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "  ✅ OPcache limpo\n";
} else {
    echo "  ⚠️  OPcache não disponível\n";
}

echo "\n";

// 4. Verificar rotas
echo "🛣️  Verificando rotas da fila...\n";
$routesFile = $projectPath . '/routes/web.php';
if (file_exists($routesFile)) {
    $content = file_get_contents($routesFile);
    $endpoints = [
        '/conversas/fila/estatisticas',
        '/conversas/fila/pegar-proxima',
        '/conversas/{id}/devolver-fila'
    ];
    
    foreach ($endpoints as $endpoint) {
        if (strpos($content, $endpoint) !== false) {
            echo "  ✅ Rota encontrada: $endpoint\n";
        } else {
            echo "  ❌ Rota NÃO encontrada: $endpoint\n";
        }
    }
} else {
    echo "  ❌ Arquivo routes/web.php não encontrado\n";
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "🎉 Deploy concluído!\n\n";

echo "📱 Acesse o PWA Chat:\n";
echo "   https://lojadaesquina.store/app/chat.html\n\n";

echo "🧪 Testar API:\n";
echo "   GET https://lojadaesquina.store/api/admin/conversas/fila/estatisticas\n";
echo "   POST https://lojadaesquina.store/api/admin/conversas/fila/pegar-proxima\n\n";

echo "🕐 Deploy executado em: " . date('Y-m-d H:i:s') . "\n";
