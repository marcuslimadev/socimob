<?php

/**
 * Configurar dados do tenant Exclusiva no banco de dados
 * Baseado nas variáveis de ambiente fornecidas
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CONFIGURAR TENANT EXCLUSIVA ===\n\n";

$tenantId = 1; // EXCLUSIVA_TENANT_ID

// Verificar se tenant existe
$tenant = DB::table('tenants')->find($tenantId);
if (!$tenant) {
    echo "❌ Tenant ID {$tenantId} não encontrado!\n";
    exit(1);
}

$tenantName = $tenant->name ?? $tenant->nome ?? $tenant->slug ?? "Tenant {$tenantId}";
echo "✅ Tenant encontrado: {$tenantName} (ID: {$tenantId})\n\n";

// Dados da configuração baseados no .env
// IMPORTANTE: Use as variáveis de ambiente reais do servidor
$allFields = [
    // Twilio
    'twilio_account_sid' => env('EXCLUSIVA_TWILIO_ACCOUNT_SID'),
    'twilio_auth_token' => env('EXCLUSIVA_TWILIO_AUTH_TOKEN'),
    'twilio_whatsapp_from' => env('EXCLUSIVA_TWILIO_WHATSAPP_FROM'),
    
    // WhatsApp do tenant
    'whatsapp_number' => env('EXCLUSIVA_TWILIO_WHATSAPP_FROM') ? str_replace('whatsapp:', '', env('EXCLUSIVA_TWILIO_WHATSAPP_FROM')) : null,
    
    // OpenAI (usando alias api_key_openai)
    'api_key_openai' => env('EXCLUSIVA_OPENAI_API_KEY'),
    'openai_model' => env('EXCLUSIVA_OPENAI_MODEL', 'gpt-4o-mini'),
    'ai_assistant_name' => env('EXCLUSIVA_AI_ASSISTANT_NAME', 'Teresa'),
    
    // Template Twilio
    'twilio_template_welcome_sid' => env('EXCLUSIVA_TENANT_TWILIO_TEMPLATE_WELCOME_SID'),
    
    // Email
    'smtp_host' => env('MAIL_HOST'),
    'smtp_port' => env('MAIL_PORT', 587),
    'smtp_username' => env('MAIL_USERNAME'),
    'smtp_password' => env('MAIL_PASSWORD'),
    'smtp_from_email' => env('MAIL_FROM_ADDRESS'),
    'smtp_from_name' => env('MAIL_FROM_NAME'),
    
    // Notificações
    'notification_email' => env('MAIL_FROM_ADDRESS'),
    'notify_new_leads' => true,
    'notify_new_properties' => true,
    'notify_new_messages' => true,
];

// Verificar quais colunas existem
$columns = DB::select("SHOW COLUMNS FROM tenant_configs");
$existingColumns = array_column($columns, 'Field');

echo "📋 Colunas disponíveis em tenant_configs:\n";
echo implode(', ', $existingColumns) . "\n\n";

$configData = [
    'updated_at' => Carbon::now()
];

// Adicionar apenas campos que existem na tabela
foreach ($allFields as $field => $value) {
    if (in_array($field, $existingColumns)) {
        $configData[$field] = $value;
    } elseif ($field === 'openai_api_key' && in_array('api_key_openai', $existingColumns)) {
        // Usar alias api_key_openai se openai_api_key não existir
        $configData['api_key_openai'] = $value;
    } else {
        echo "⚠️  Campo '{$field}' não existe na tabela - pulando\n";
    }
}

echo "\n";

// Verificar se já existe configuração
$existingConfig = DB::table('tenant_configs')->where('tenant_id', $tenantId)->first();

if ($existingConfig) {
    echo "📝 Atualizando configuração existente...\n";
    DB::table('tenant_configs')
        ->where('tenant_id', $tenantId)
        ->update($configData);
    echo "✅ Configuração atualizada!\n\n";
} else {
    echo "📝 Criando nova configuração...\n";
    $configData['tenant_id'] = $tenantId;
    $configData['created_at'] = Carbon::now();
    DB::table('tenant_configs')->insert($configData);
    echo "✅ Configuração criada!\n\n";
}

// Exibir configuração atual
echo "📊 Configuração do Tenant:\n";
echo "─────────────────────────────────────────\n";
$config = DB::table('tenant_configs')->where('tenant_id', $tenantId)->first();

echo "🔧 Twilio:\n";
echo "  Account SID: " . substr($config->twilio_account_sid ?? 'N/A', 0, 20) . "...\n";
echo "  WhatsApp From: {$config->twilio_whatsapp_from}\n";
echo "  WhatsApp Number: {$config->whatsapp_number}\n";
echo "  Template Welcome SID: " . substr($config->twilio_template_welcome_sid ?? 'N/A', 0, 20) . "...\n\n";

echo "🤖 OpenAI:\n";
echo "  Model: {$config->openai_model}\n";
echo "  Assistant Name: {$config->ai_assistant_name}\n";
echo "  API Key: " . substr($config->openai_api_key ?? 'N/A', 0, 20) . "...\n\n";

echo "📧 Email:\n";
echo "  Host: {$config->smtp_host}\n";
echo "  Port: {$config->smtp_port}\n";
echo "  From: {$config->smtp_from_email} ({$config->smtp_from_name})\n";
echo "  Notification Email: {$config->notification_email}\n\n";

echo "🔔 Notificações:\n";
echo "  Novos Leads: " . ($config->notify_new_leads ? '✅' : '❌') . "\n";
echo "  Novos Imóveis: " . ($config->notify_new_properties ? '✅' : '❌') . "\n";
echo "  Novas Mensagens: " . ($config->notify_new_messages ? '✅' : '❌') . "\n\n";

echo "─────────────────────────────────────────\n";
echo "✅ Configuração completa!\n";
