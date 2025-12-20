<?php

echo "=== DIAGNOSTICANDO ERRO 404 ===\n\n";

// URLs comuns que podem estar falhando
$urls = [
    'http://127.0.0.1:8000/',
    'http://127.0.0.1:8000/api/health',
    'http://127.0.0.1:8000/app/',
    'http://127.0.0.1:8000/app/index.html',
    'http://127.0.0.1:8000/app/imoveis.html',
    'http://127.0.0.1:8000/api/admin/settings',
    'http://127.0.0.1:8000/api/admin/imoveis/importar',
    'http://127.0.0.1:8000/test-connectivity.html'
];

foreach ($urls as $url) {
    echo "Testando: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ Erro de conexão: $error\n";
    } elseif ($httpCode === 200) {
        echo "✅ Status: $httpCode - OK\n";
        // Verificar se é HTML ou JSON
        if (strpos($response, '<html>') !== false || strpos($response, '<!DOCTYPE') !== false) {
            echo "   Tipo: HTML\n";
        } elseif (json_decode($response)) {
            echo "   Tipo: JSON\n";
        } else {
            echo "   Tipo: Outro (" . substr($response, 0, 50) . "...)\n";
        }
    } elseif ($httpCode === 404) {
        echo "❌ Status: $httpCode - NOT FOUND\n";
        echo "   Resposta: " . substr($response, 0, 200) . "...\n";
    } elseif ($httpCode === 401) {
        echo "🔐 Status: $httpCode - UNAUTHORIZED (esperado para rotas protegidas)\n";
    } elseif ($httpCode === 500) {
        echo "🔥 Status: $httpCode - INTERNAL SERVER ERROR\n";
        echo "   Erro: " . substr($response, 0, 300) . "...\n";
    } else {
        echo "⚠️  Status: $httpCode\n";
        echo "   Resposta: " . substr($response, 0, 100) . "...\n";
    }
    
    echo "\n";
    usleep(100000); // 0.1 segundo de pausa
}

echo "=== VERIFICANDO LOGS DO SERVIDOR ===\n\n";

// Verificar se existe algum log de erro
$logFile = __DIR__ . '/storage/logs/lumen-' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    echo "Log encontrado: $logFile\n";
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $recentLines = array_slice($lines, -10); // Últimas 10 linhas
    
    foreach ($recentLines as $line) {
        if (!empty(trim($line))) {
            echo "  " . $line . "\n";
        }
    }
} else {
    echo "Nenhum log encontrado em: $logFile\n";
}

echo "\n=== DIAGNÓSTICO CONCLUÍDO ===\n";