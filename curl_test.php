<?php

echo "Testando API de imóveis com curl...\n\n";

$ch = curl_init('http://127.0.0.1:8000/api/portal/imoveis');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

echo "HTTP Code: $httpCode\n\n";

$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

echo "Headers:\n$headers\n";
echo "Body:\n$body\n";

curl_close($ch);