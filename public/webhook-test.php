<?php
/**
 * Endpoint de teste SIMPLES para webhook
 * Apenas loga tudo que chegar
 */

// Log em arquivo direto
$logFile = __DIR__ . '/../storage/logs/webhook-test-' . date('Y-m-d') . '.log';
$timestamp = date('Y-m-d H:i:s');
$method = $_SERVER['REQUEST_METHOD'];
$data = file_get_contents('php://input');
$getParams = $_GET;
$postParams = $_POST;

$logEntry = "[$timestamp] $method request received\n";
$logEntry .= "GET params: " . json_encode($getParams) . "\n";
$logEntry .= "POST params: " . json_encode($postParams) . "\n";
$logEntry .= "Raw body: " . $data . "\n";
$logEntry .= "Headers: " . json_encode(getallheaders()) . "\n";
$logEntry .= str_repeat("=", 80) . "\n\n";

file_put_contents($logFile, $logEntry, FILE_APPEND);

// Responder sempre 200
http_response_code(200);
header('Content-Type: text/plain');
echo "OK";
