<?php
/**
 * Visualizar log de teste do webhook
 */

$token = $_GET['token'] ?? '';
if ($token !== 'temp-debug-2025') {
    http_response_code(403);
    die('Access denied');
}

$logFile = __DIR__ . '/../storage/logs/webhook-test-' . date('Y-m-d') . '.log';

header('Content-Type: text/plain; charset=utf-8');
echo "═══════════════════════════════════════════════════════════════\n";
echo "LOG DE TESTE DO WEBHOOK - " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

if (file_exists($logFile)) {
    echo file_get_contents($logFile);
} else {
    echo "⚠️ Nenhum registro ainda.\n";
    echo "Arquivo esperado: $logFile\n";
}
