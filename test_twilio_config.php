<?php

require_once 'bootstrap/app.php';

use Illuminate\Support\Facades\Log;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║      🧪 TESTE DE CONFIGURAÇÃO DO TWILIO WHATSAPP              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Verificar variáveis de ambiente
$config = [
    'TWILIO_ACCOUNT_SID' => env('TWILIO_ACCOUNT_SID'),
    'TWILIO_AUTH_TOKEN' => env('TWILIO_AUTH_TOKEN'),
    'TWILIO_WHATSAPP_FROM' => env('TWILIO_WHATSAPP_FROM'),
    'TWILIO_WHATSAPP_NUMBER' => env('TWILIO_WHATSAPP_NUMBER'),
    'AI_ASSISTANT_NAME' => env('AI_ASSISTANT_NAME', 'Teresa'),
];

echo "📋 Variáveis do Twilio:\n";
echo "─────────────────────────────────────────────────────────────────\n";

foreach ($config as $key => $value) {
    if (strpos($value, 'sua_') !== false || empty($value)) {
        echo "❌ $key: NÃO CONFIGURADO\n";
    } else if (strlen($value) > 20) {
        echo "✅ $key: " . substr($value, 0, 20) . "...\n";
    } else {
        echo "✅ $key: $value\n";
    }
}

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║                  PRÓXIMOS PASSOS                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$allConfigured = true;
foreach ($config as $key => $value) {
    if (strpos($value, 'sua_') !== false || empty($value)) {
        $allConfigured = false;
        break;
    }
}

if (!$allConfigured) {
    echo "1️⃣  Obtenha as credenciais do Twilio:\n";
    echo "   📱 Acesse: https://console.twilio.com/\n";
    echo "   👤 Faça login ou crie uma conta\n";
    echo "   🔑 Copie seu Account SID e Auth Token\n\n";
    
    echo "2️⃣  Configure o WhatsApp Business no Twilio:\n";
    echo "   📲 Na seção Products → Messaging → Services\n";
    echo "   ⚙️  Crie um novo serviço ou use um existente\n";
    echo "   ✅ Conecte seu número do WhatsApp Business\n\n";
    
    echo "3️⃣  Atualize o arquivo .env:\n";
    echo "   📝 Edite: backend/.env\n";
    echo "   🔧 Substitua as credenciais placeholder pelas reais\n";
    echo "   💾 Salve e reinicie o servidor\n\n";
} else {
    echo "✅ Twilio configurado com sucesso!\n\n";
    echo "📝 Próximos passos:\n";
    echo "1. Adicione a rota de webhook do Twilio\n";
    echo "2. Configure o URL no dashboard do Twilio\n";
    echo "3. Teste enviando uma mensagem\n\n";
    
    echo "🌐 URL do Webhook (adicionar no Twilio):\n";
    echo "   POST: http://seu-dominio.com/webhook/whatsapp\n\n";
}

echo "═════════════════════════════════════════════════════════════════\n";
