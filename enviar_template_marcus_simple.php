<?php
/**
 * Envio DIRETO de template Twilio para Marcus André
 * SEM dependências do framework (standalone)
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Carregar .env manualmente
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

echo "===================================================================\n";
echo "  ENVIO TEMPLATE TWILIO: contatoinicial para Marcus André         \n";
echo "===================================================================\n\n";

$accountSid = getenv('EXCLUSIVA_TWILIO_ACCOUNT_SID');
$authToken = getenv('EXCLUSIVA_TWILIO_AUTH_TOKEN');
$whatsappFrom = getenv('EXCLUSIVA_TWILIO_WHATSAPP_FROM');
$templateSid = 'HX4f61c2b07ceef4afc402a9c4753300df';
$destinatario = '+5592992287144';

echo "Configurações:\n";
echo "- Account SID: " . substr($accountSid, 0, 10) . "...\n";
echo "- Auth Token: " . substr($authToken, 0, 10) . "...\n";
echo "- De: $whatsappFrom\n";
echo "- Para: $destinatario\n";
echo "- Template SID: $templateSid\n\n";

if (empty($accountSid) || empty($authToken) || empty($whatsappFrom)) {
    echo "ERRO: Credenciais Twilio não configuradas no .env\n";
    exit(1);
}

// Normalizar número
$to = trim($destinatario);
if (strpos($to, 'whatsapp:') === false) {
    $to = 'whatsapp:' . $to;
}

echo "Preparando envio...\n";
echo "- Destino normalizado: $to\n\n";

// Montar dados para API
$url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
$data = [
    'From' => $whatsappFrom,
    'To' => $to,
    'ContentSid' => $templateSid
];

echo "Enviando para API Twilio...\n\n";

// Executar cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "{$accountSid}:{$authToken}");
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$responseData = json_decode($response, true);

echo "===================================================================\n";
echo "                      RESULTADO DO ENVIO                           \n";
echo "===================================================================\n\n";

echo "HTTP Code: $httpCode\n";

if ($httpCode === 201) {
    echo "Status: SUCESSO ✓✓✓\n\n";
    echo "Message SID: " . ($responseData['sid'] ?? 'N/A') . "\n";
    echo "Status Twilio: " . ($responseData['status'] ?? 'N/A') . "\n";
    echo "Data de envio: " . ($responseData['date_created'] ?? 'N/A') . "\n\n";
    echo "✓✓✓ MENSAGEM ENVIADA COM SUCESSO! ✓✓✓\n";
    echo "Aguarde alguns segundos para receber no WhatsApp\n";
} else {
    echo "Status: ERRO ✗✗✗\n\n";
    if (!empty($error)) {
        echo "Erro cURL: $error\n\n";
    }
    if ($responseData) {
        echo "Resposta da API:\n";
        echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

        if (isset($responseData['message'])) {
            echo "Mensagem: " . $responseData['message'] . "\n";
        }
        if (isset($responseData['code'])) {
            echo "Código: " . $responseData['code'] . "\n";
        }
    }
}

echo "\n===================================================================\n";
echo "                         FIM                                       \n";
echo "===================================================================\n";
