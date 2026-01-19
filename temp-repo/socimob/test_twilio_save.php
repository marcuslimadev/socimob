<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

echo "🧪 Testando salvamento de credenciais Twilio...\n\n";

try {
    // Buscar primeiro tenant
    $tenant = DB::table('tenants')->first();
    if (!$tenant) {
        echo "❌ Nenhum tenant encontrado\n";
        exit(1);
    }
    
    echo "✓ Tenant encontrado: {$tenant->name} (ID: {$tenant->id})\n";
    
    // Buscar config
    $config = DB::table('tenant_configs')->where('tenant_id', $tenant->id)->first();
    if (!$config) {
        echo "  Criando config...\n";
        DB::table('tenant_configs')->insert([
            'tenant_id' => $tenant->id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        $config = DB::table('tenant_configs')->where('tenant_id', $tenant->id)->first();
    }
    
    echo "✓ Config encontrado (ID: {$config->id})\n\n";
    
    // Dados de teste
    $testData = [
        'twilio_account_sid' => 'ACtest1234567890abcdef',
        'twilio_auth_token' => 'test_auth_token_12345',
        'twilio_whatsapp_from' => 'whatsapp:+5511999999999',
        'api_key_openai' => 'sk-test-openai-key-12345',
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    echo "📝 Salvando dados de teste:\n";
    foreach ($testData as $key => $value) {
        if ($key !== 'updated_at') {
            echo "  - $key: $value\n";
        }
    }
    
    // Atualizar
    DB::table('tenant_configs')
        ->where('id', $config->id)
        ->update($testData);
    
    echo "\n💾 Dados salvos!\n\n";
    
    // Recarregar e verificar
    $config = DB::table('tenant_configs')->where('id', $config->id)->first();
    
    echo "✅ Verificando dados salvos:\n";
    echo "  - twilio_account_sid: {$config->twilio_account_sid}\n";
    echo "  - twilio_auth_token: {$config->twilio_auth_token}\n";
    echo "  - twilio_whatsapp_from: {$config->twilio_whatsapp_from}\n";
    echo "  - api_key_openai: {$config->api_key_openai}\n";
    
    $allMatch = (
        $config->twilio_account_sid === $testData['twilio_account_sid'] &&
        $config->twilio_auth_token === $testData['twilio_auth_token'] &&
        $config->twilio_whatsapp_from === $testData['twilio_whatsapp_from'] &&
        $config->api_key_openai === $testData['api_key_openai']
    );
    
    if ($allMatch) {
        echo "\n✅ TODOS OS DADOS SALVOS CORRETAMENTE!\n";
        echo "\n🎉 O sistema agora pode salvar credenciais Twilio e OpenAI!\n";
    } else {
        echo "\n❌ ERRO: Alguns dados não foram salvos corretamente\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
