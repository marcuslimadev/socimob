<?php
$ch = curl_init('http://127.0.0.1:8000/api/portal/imoveis');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo 'HTTP Code: ' . $httpCode . PHP_EOL;
echo 'Response length: ' . strlen($response) . PHP_EOL;

if (strlen($response) < 2000) {
    echo 'Response: ' . $response . PHP_EOL;
} else {
    echo 'Response (first 1000 chars): ' . substr($response, 0, 1000) . '...' . PHP_EOL;
}