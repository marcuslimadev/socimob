<?php
$ch = curl_init('http://127.0.0.1:8000/api/portal/imoveis');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer eyJ1c2VyX2lkIjoxLCJ0aW1lc3RhbXAiOjE3MzQ2MzQ4MDAsInNlY3JldCI6InRlc3Rfc2VjcmV0In0=',
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo 'HTTP Code: ' . $httpCode . PHP_EOL;
echo 'Response length: ' . strlen($response) . PHP_EOL;

if (strlen($response) < 1000) {
    echo 'Response: ' . $response . PHP_EOL;
} else {
    echo 'Response (first 1000 chars): ' . substr($response, 0, 1000) . '...' . PHP_EOL;
}