<?php

/**
 * Configurar dados do tenant Exclusiva no banco de dados
 * Execução: php setup_exclusiva_configs.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CONFIGURAR TENANT EXCLUSIVA ===\n\n";

$tenantId = 1; // EXCLUSIVA_TENANT_ID

// Verificar se tenant existe
$tenant = DB::table('tenants')->find($tenantId);
if (!$tenant) {
    echo "❌ Tenant ID {$tenantId} não encontrado!\n";
    echo "Criando tenant...\n";
    
    DB::table('tenants')->insert([
        'id' => $tenantId,
        'nome' => 'Exclusiva Imóveis',
        'slug' => 'alexsandra-fialho',
        'dominio' => 'exclusivalarimoveis.com',
        'email' => 'contato@exclusivalarimoveis.com.br',
        'ativo' => true,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "✅ Tenant criado!\n\n";
}

echo "📊 Tenant: {$tenant->nome ?? 'Exclusiva Imóveis'}\n\n";

// Verificar se configuração já existe
$config = DB::table('tenant_configs')->where('tenant_id', $tenantId)->first();

$configData = [
    // Twilio - usar variáveis de ambiente
    'twilio_account_sid' => env('EXCLUSIVA_TWILIO_ACCOUNT_SID'),
    'twilio_auth_token' => env('EXCLUSIVA_TWILIO_AUTH_TOKEN'),
    'twilio_whatsapp_from' => env('EXCLUSIVA_TWILIO_WHATSAPP_FROM'),
    'whatsapp_number' => '+553173341150',
    
    // OpenAI - usar variáveis de ambiente
    'api_key_openai' => env('EXCLUSIVA_OPENAI_API_KEY'),
    'openai_model' => env('EXCLUSIVA_OPENAI_MODEL', 'gpt-4o-mini'),
    'ai_assistant_name' => env('EXCLUSIVA_AI_ASSISTANT_NAME', 'Teresa'),
    
    // Email SMTP
    'smtp_host' => 'smtp.titan.email',
    'smtp_port' => 587,
    'smtp_username' => 'alert@socimob.com',
    'smtp_password' => 'MundoMelhor@10',
    'smtp_from_email' => 'alert@socimob.com',
    'smtp_from_name' => 'SOCIMOB',
    
    // Notificações
    'notify_new_leads' => true,
    'notify_new_properties' => true,
    'notify_new_messages' => true,
    'notification_email' => 'contato@exclusivalarimoveis.com.br',
    
    // Cores do tema
    'primary_color' => '#1e40af',
    'secondary_color' => '#64748b',
    'accent_color' => '#f59e0b',
    
    // Limites
    'max_images_per_property' => 20,
    'max_properties' => 5000,
    'max_leads' => 10000,
    
    'updated_at' => now()
];

if ($config) {
    // Atualizar
    DB::table('tenant_configs')
        ->where('tenant_id', $tenantId)
        ->update($configData);
    
    echo "✅ Configurações ATUALIZADAS com sucesso!\n\n";
} else {
    // Criar
    $configData['tenant_id'] = $tenantId;
    $configData['created_at'] = now();
    
    DB::table('tenant_configs')->insert($configData);
    
    echo "✅ Configurações CRIADAS com sucesso!\n\n";
}

// Exibir configuração atual
echo "📋 Configurações salvas:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$current = DB::table('tenant_configs')->where('tenant_id', $tenantId)->first();

echo "🔐 TWILIO:\n";
echo "  Account SID: " . substr($current->twilio_account_sid, 0, 10) . "...\n";
echo "  Auth Token: " . substr($current->twilio_auth_token, 0, 10) . "...\n";
echo "  WhatsApp From: {$current->twilio_whatsapp_from}\n";
echo "  WhatsApp Number: {$current->whatsapp_number}\n\n";

echo "🤖 OPENAI:\n";
echo "  API Key: " . substr($current->api_key_openai ?? 'não configurado', 0, 20) . "...\n";
echo "  Model: {$current->openai_model}\n";
echo "  Assistant Name: {$current->ai_assistant_name}\n\n";

echo "📧 EMAIL:\n";
echo "  SMTP Host: {$current->smtp_host}\n";
echo "  SMTP Port: {$current->smtp_port}\n";
echo "  From: {$current->smtp_from_email}\n";
echo "  From Name: {$current->smtp_from_name}\n\n";

echo "🔔 NOTIFICAÇÕES:\n";
echo "  Novos Leads: " . ($current->notify_new_leads ? 'SIM' : 'NÃO') . "\n";
echo "  Email: {$current->notification_email}\n\n";

echo "🎨 TEMA:\n";
echo "  Primary: {$current->primary_color}\n";
echo "  Secondary: {$current->secondary_color}\n";
echo "  Accent: {$current->accent_color}\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Configuração completa!\n";
