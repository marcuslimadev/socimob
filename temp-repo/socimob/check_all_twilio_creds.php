<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

echo "🔍 Verificando todas as colunas Twilio do tenant_id=1...\n\n";

try {
    $config = DB::table('tenant_configs')->where('tenant_id', 1)->first();
    
    if (!$config) {
        echo "❌ Config não encontrado\n";
        exit(1);
    }
    
    echo "📊 Credenciais Twilio:\n";
    echo "  - twilio_account_sid: " . ($config->twilio_account_sid ?? 'NULL') . "\n";
    echo "  - twilio_auth_token: " . ($config->twilio_auth_token ?? 'NULL') . "\n";
    echo "  - twilio_whatsapp_from: " . ($config->twilio_whatsapp_from ?? 'NULL') . "\n";
    echo "\n📊 API Keys:\n";
    echo "  - api_key_openai: " . ($config->api_key_openai ?? 'NULL') . "\n";
    
    // Verificar se auth_token está vazio
    if (empty($config->twilio_auth_token)) {
        echo "\n⚠️  PROBLEMA ENCONTRADO: twilio_auth_token está VAZIO!\n";
        echo "   Isso impede o Twilio de autenticar e enviar mensagens.\n";
        echo "\n🔧 Solução: Preencha o Auth Token em Configurações → Integrações\n";
    } else {
        echo "\n✅ Todas as credenciais estão preenchidas!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
