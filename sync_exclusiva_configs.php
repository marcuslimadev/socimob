<?php

/**
 * Script para sincronizar configurações da Exclusiva do .env para o banco de dados
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

try {
    echo "=== SINCRONIZAÇÃO DE CONFIGURAÇÕES EXCLUSIVA ===\n\n";
    
    $tenantId = (int) env('EXCLUSIVA_TENANT_ID', 1);
    
    echo "Buscando tenant ID: {$tenantId}\n";
    
    $tenant = DB::table('tenants')->where('id', $tenantId)->first();
    
    if (!$tenant) {
        echo "❌ Tenant não encontrado!\n";
        exit(1);
    }
    
    echo "✓ Tenant encontrado: {$tenant->name}\n\n";
    
    // Preparar dados para atualização
    $updateData = [
        // Twilio
        'twilio_account_sid' => env('EXCLUSIVA_TWILIO_ACCOUNT_SID'),
        'twilio_auth_token' => env('EXCLUSIVA_TWILIO_AUTH_TOKEN'),
        'twilio_whatsapp_from' => env('EXCLUSIVA_TWILIO_WHATSAPP_FROM'),
        'twilio_template_welcome_sid' => env('EXCLUSIVA_TENANT_TWILIO_TEMPLATE_WELCOME_SID'),
        
        // OpenAI
        'openai_api_key' => env('EXCLUSIVA_OPENAI_API_KEY'),
        'openai_model' => env('EXCLUSIVA_OPENAI_MODEL', 'gpt-4o-mini'),
        'ai_assistant_name' => env('EXCLUSIVA_AI_ASSISTANT_NAME', 'Teresa'),
        
        // Email
        'mail_driver' => env('MAIL_DRIVER', 'smtp'),
        'mail_host' => env('MAIL_HOST'),
        'mail_port' => (int) env('MAIL_PORT', 587),
        'mail_username' => env('MAIL_USERNAME'),
        'mail_password' => env('MAIL_PASSWORD'),
        'mail_encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'mail_from_address' => env('MAIL_FROM_ADDRESS'),
        'mail_from_name' => env('MAIL_FROM_NAME', 'SOCIMOB'),
        
        // Domínio
        'domain' => env('EXCLUSIVA_TENANT_DOMAIN', $tenant->domain),
        'slug' => env('EXCLUSIVA_TENANT_SLUG', $tenant->slug),
        
        // API Token
        'api_token' => env('EXCLUSIVA_API_TOKEN', $tenant->api_token),
        
        // Email Chaves na Mão
        'contact_email' => env('EXCLUSIVA_MAIL_CHAVES_NA_MAO', $tenant->contact_email),
        
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    
    // Remover valores nulos para não sobrescrever dados existentes
    $updateData = array_filter($updateData, function($value) {
        return $value !== null && $value !== '';
    });
    
    echo "Atualizando configurações:\n";
    foreach ($updateData as $key => $value) {
        if (in_array($key, ['twilio_auth_token', 'openai_api_key', 'mail_password', 'api_token'])) {
            echo "  - {$key}: [REDACTED]\n";
        } else {
            $displayValue = is_string($value) && strlen($value) > 50 
                ? substr($value, 0, 50) . '...' 
                : $value;
            echo "  - {$key}: {$displayValue}\n";
        }
    }
    
    echo "\n";
    
    $affected = DB::table('tenants')
        ->where('id', $tenantId)
        ->update($updateData);
    
    if ($affected > 0) {
        echo "✓ Configurações atualizadas com sucesso!\n";
        echo "✓ {$affected} registro(s) atualizado(s)\n";
    } else {
        echo "⚠ Nenhum registro foi atualizado (dados já estavam sincronizados)\n";
    }
    
    // Mostrar configuração final
    $updatedTenant = DB::table('tenants')->where('id', $tenantId)->first();
    echo "\n=== CONFIGURAÇÃO FINAL ===\n";
    echo "Nome: {$updatedTenant->name}\n";
    echo "Domínio: {$updatedTenant->domain}\n";
    echo "Slug: {$updatedTenant->slug}\n";
    echo "Email: {$updatedTenant->contact_email}\n";
    echo "Twilio SID: " . ($updatedTenant->twilio_account_sid ? 'Configurado ✓' : 'Não configurado') . "\n";
    echo "OpenAI: " . ($updatedTenant->openai_api_key ? 'Configurado ✓' : 'Não configurado') . "\n";
    echo "Email SMTP: " . ($updatedTenant->mail_host ? 'Configurado ✓' : 'Não configurado') . "\n";
    echo "\n✓ Sincronização concluída!\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
