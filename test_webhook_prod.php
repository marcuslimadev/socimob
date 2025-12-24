<?php
/**
 * Teste do Webhook WhatsApp em Produção
 * Verifica se o endpoint está respondendo corretamente
 */

echo "\n";
echo "=================================================\n";
echo "  TESTE WEBHOOK WHATSAPP - PRODUÇÃO\n";
echo "=================================================\n\n";

$prodUrl = 'https://exclusivalarimoveis.com';
$webhookPath = '/webhook/whatsapp';
$fullUrl = $prodUrl . $webhookPath;

echo "🌐 URL de Produção: $fullUrl\n\n";

// ==========================================
// TESTE 1: GET (Validação)
// ==========================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 TESTE 1: GET /webhook/whatsapp (Validação)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$ch = curl_init($fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para teste
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ ERRO: $error\n";
} else {
    echo "✓ Status HTTP: $httpCode\n";
    if ($httpCode == 200) {
        echo "✓ Validação GET funcionando!\n";
    } else {
        echo "⚠ Status inesperado. Resposta:\n";
        echo substr($response, 0, 500) . "\n";
    }
}

echo "\n";

// ==========================================
// TESTE 2: POST (Mensagem de Teste)
// ==========================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 TESTE 2: POST /webhook/whatsapp (Mensagem)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Simular payload do Twilio
$twilioPayload = [
    'MessageSid' => 'TEST' . time(),
    'AccountSid' => 'TEST_ACCOUNT',
    'From' => 'whatsapp:+5521999999999',
    'To' => 'whatsapp:+5521988888888',
    'Body' => 'Teste de webhook - ' . date('H:i:s'),
    'ProfileName' => 'Teste Produção',
    'FromCity' => 'Rio de Janeiro',
    'FromState' => 'RJ',
    'FromCountry' => 'BR'
];

$ch = curl_init($fullUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($twilioPayload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ ERRO: $error\n";
} else {
    echo "✓ Status HTTP: $httpCode\n";
    if ($httpCode == 200) {
        echo "✓ Webhook POST funcionando!\n";
    } else {
        echo "⚠ Status inesperado. Resposta:\n";
        echo substr($response, 0, 500) . "\n";
    }
}

echo "\n";

// ==========================================
// TESTE 3: Verificar Headers
// ==========================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 TESTE 3: Verificar Configuração do Servidor\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$ch = curl_init($fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "✓ Métodos permitidos: " . ($info['http_code'] == 200 ? 'GET, POST' : 'Verificar manualmente') . "\n";
echo "✓ Content-Type: " . ($info['content_type'] ?? 'N/A') . "\n";

echo "\n";

// ==========================================
// RESUMO E PRÓXIMOS PASSOS
// ==========================================
echo "=================================================\n";
echo "  RESUMO E PRÓXIMOS PASSOS\n";
echo "=================================================\n\n";

echo "✅ Verificações realizadas:\n";
echo "   • GET /webhook/whatsapp para validação\n";
echo "   • POST /webhook/whatsapp para mensagens\n\n";

echo "📝 Se o erro 405 persistir, verifique:\n\n";
echo "1. Configuração do .htaccess:\n";
echo "   • Arquivo: public/.htaccess\n";
echo "   • Verificar se RewriteEngine está On\n";
echo "   • Verificar se há bloqueios de método\n\n";

echo "2. Configuração do Apache/Nginx:\n";
echo "   • Verificar se mod_rewrite está ativo\n";
echo "   • Verificar se AllowOverride All está configurado\n";
echo "   • Verificar logs: /var/log/apache2/error.log\n\n";

echo "3. Painel Twilio:\n";
echo "   • Configuração do Sandbox: https://console.twilio.com/us1/develop/sms/settings/whatsapp-sandbox\n";
echo "   • Webhook URL: $fullUrl\n";
echo "   • Método HTTP: POST\n\n";

echo "4. Testar localmente primeiro:\n";
echo "   • php test_webhook_whatsapp.php\n";
echo "   • Garantir que funciona antes de testar em prod\n\n";

echo "=================================================\n\n";
