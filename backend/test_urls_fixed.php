<?php

echo "=== TESTANDO URLS APÓS CORREÇÃO ===\n\n";

$urls = [
    'http://127.0.0.1:8000/',
    'http://127.0.0.1:8000/api/health',
    'http://127.0.0.1:8000/app/',
    'http://127.0.0.1:8000/app/imoveis.html',
    'http://127.0.0.1:8000/api/admin/settings'
];

foreach ($urls as $url) {
    echo "Testando: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ Status: $httpCode\n";
    } elseif ($httpCode === 401) {
        echo "🔐 Status: $httpCode (Unauthorized - esperado)\n";
    } elseif ($httpCode === 404) {
        echo "❌ Status: $httpCode (Not Found)\n";
    } else {
        echo "⚠️  Status: $httpCode\n";
    }
    
    // Mostrar primeiras linhas da resposta para debug
    $lines = explode("\n", $response);
    echo "Primeira linha: " . trim($lines[0]) . "\n";
    echo "\n";
}

echo "=== TESTE CONCLUÍDO ===\n";