<?php
/**
 * Script para ler logs de produção via HTTP
 */

// Configurar para exibir erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Simular requisição HTTP para buscar logs
$url = 'https://lojadaesquina.store/storage/logs/lumen-' . date('Y-m-d') . '.log';

echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║         📋 LOGS DE PRODUÇÃO - " . date('Y-m-d') . "                     ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";

echo "🔍 Tentando acessar: $url\n\n";

// Tentar via cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && $response) {
    echo "✅ Log encontrado!\n";
    echo "═══════════════════════════════════════════════════════════════════\n\n";
    
    // Filtrar apenas linhas relevantes do webhook
    $lines = explode("\n", $response);
    $webhookLines = [];
    $collecting = false;
    
    foreach ($lines as $line) {
        if (stripos($line, 'WEBHOOK') !== false || 
            stripos($line, 'WhatsApp') !== false ||
            stripos($line, 'Twilio') !== false ||
            stripos($line, 'Conversa criada') !== false ||
            stripos($line, 'Lead') !== false ||
            stripos($line, 'Mensagem salva') !== false) {
            $webhookLines[] = $line;
            $collecting = true;
        } elseif ($collecting && (stripos($line, 'ERROR') !== false || stripos($line, 'INFO') !== false)) {
            $webhookLines[] = $line;
        }
    }
    
    if (count($webhookLines) > 0) {
        echo "📱 Entradas relacionadas ao WhatsApp/Webhook:\n\n";
        foreach (array_slice($webhookLines, -50) as $line) {
            echo $line . "\n";
        }
    } else {
        echo "⚠️ Nenhuma entrada de webhook encontrada nos logs.\n";
        echo "Exibindo últimas 30 linhas do log:\n\n";
        foreach (array_slice($lines, -30) as $line) {
            echo $line . "\n";
        }
    }
} else {
    echo "❌ Não foi possível acessar o log via HTTP (Status: $httpCode)\n";
    echo "Os logs não são publicamente acessíveis (o que é bom para segurança!).\n\n";
    echo "💡 Alternativas:\n";
    echo "   1. Acessar via SSH: ssh usuario@lojadaesquina.store\n";
    echo "      tail -f /home/usuario/domains/exclusivalarimoveis.com/public_html/storage/logs/lumen-" . date('Y-m-d') . ".log\n\n";
    echo "   2. Usar cPanel File Manager\n\n";
    echo "   3. Criar endpoint temporário de logs (não recomendado em produção)\n";
}

echo "\n";
