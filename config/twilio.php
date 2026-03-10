<?php

/**
 * Configurações do Twilio
 *
 * IMPORTANTE: Usar config('twilio.xxx') em vez de env() diretamente
 * para evitar problemas de cache e leitura de variáveis de ambiente.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Credenciais da conta Twilio
    |--------------------------------------------------------------------------
    */
    'account_sid' => env('EXCLUSIVA_TWILIO_ACCOUNT_SID'),
    'auth_token' => env('EXCLUSIVA_TWILIO_AUTH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Número de origem para WhatsApp
    |--------------------------------------------------------------------------
    | Formato: whatsapp:+5531XXXXXXXXX
    */
    'whatsapp_from' => env('EXCLUSIVA_TWILIO_WHATSAPP_FROM'),

    /*
    |--------------------------------------------------------------------------
    | Número de origem para SMS
    |--------------------------------------------------------------------------
    | Formato: +1XXXXXXXXXX (número Twilio para SMS)
    */
    'sms_from' => env('EXCLUSIVA_TWILIO_SMS_FROM', env('EXCLUSIVA_TWILIO_PHONE_NUMBER')),

    /*
    |--------------------------------------------------------------------------
    | Template SID para mensagens de boas-vindas
    |--------------------------------------------------------------------------
    */
    'template_welcome_sid' => env('EXCLUSIVA_TENANT_TWILIO_TEMPLATE_WELCOME_SID'),

    /*
    |--------------------------------------------------------------------------
    | ID do tenant padrão para resolução de webhook
    |--------------------------------------------------------------------------
    */
    'webhook_tenant_id' => env('WEBHOOK_TENANT_ID'),
];
