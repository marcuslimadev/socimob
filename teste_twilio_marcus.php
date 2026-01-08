<?php
require __DIR__ . '/bootstrap/app.php';

echo "═══════════════════════════════════════════════════════════════\n";
echo " TESTE DIRETO TWILIO - ENVIAR PARA MARCUS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$accountSid = env('EXCLUSIVA_TWILIO_ACCOUNT_SID');
$authToken = env('EXCLUSIVA_TWILIO_AUTH_TOKEN');
$from = env('EXCLUSIVA_TWILIO_WHATSAPP_FROM');
$to = 'whatsapp:+5592992287144';

echo "Account SID: {$accountSid}\n";
echo "Auth Token: " . substr($authToken, 0, 10) . "...\n";
echo "From: {$from}\n";
echo "To: {$to}\n\n";

echo "Enviando mensagem de teste...\n\n";

$url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

$data = [
    'From' => $from,
    'To' => $to,
    'Body' => '🔔 TESTE DIRETO: Esta é uma mensagem de teste do sistema SOCIMOB. Se você receber esta mensagem, o sistema está funcionando! Responda "OK" para confirmar.'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_USERPWD, "{$accountSid}:{$authToken}");
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

if ($error) {
    echo "❌ Curl Error: {$error}\n";
}

$responseData = json_decode($response, true);

if ($httpCode === 201 && isset($responseData['sid'])) {
    echo "✅ SUCESSO! Mensagem enviada!\n";
    echo "Message SID: {$responseData['sid']}\n";
    echo "Status: {$responseData['status']}\n";
    echo "\n";
    echo "⚠️  IMPORTANTE:\n";
    echo "   - Se você está usando Twilio SANDBOX, o número +5592992287144 precisa estar registrado\n";
    echo "   - Para registrar, envie a mensagem 'join [código]' de +5592992287144 para {$from}\n";
    echo "   - Código do sandbox geralmente é algo como 'join shadow-apple'\n";
} else {
    echo "❌ ERRO ao enviar!\n";
    if (isset($responseData['message'])) {
        echo "Mensagem de erro: {$responseData['message']}\n";
    }
    if (isset($responseData['code'])) {
        echo "Código de erro: {$responseData['code']}\n";
        
        // Erros comuns
        if ($responseData['code'] == 21608) {
            echo "\n⚠️  ERRO 21608: O número destino não está verificado no Twilio Sandbox!\n";
            echo "   SOLUÇÃO: O número +5592992287144 precisa enviar 'join [código-sandbox]' para {$from}\n";
        } elseif ($responseData['code'] == 63007) {
            echo "\n⚠️  ERRO 63007: O número destino não pode receber mensagens WhatsApp\n";
        }
    }
}
