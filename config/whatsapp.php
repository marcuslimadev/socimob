<?php

/**
 * Configurações da API WhatsApp Business (Meta Cloud API)
 *
 * IMPORTANTE: Usar config('whatsapp.xxx') em vez de env() diretamente.
 *
 * Pré-requisitos:
 *  1. Conta no Meta Business Manager (business.facebook.com)
 *  2. App no Meta for Developers com produto "WhatsApp"
 *  3. Número de telefone verificado e aprovado
 *  4. Access Token permanente (System User) ou temporário
 *
 * Documentação: https://developers.facebook.com/docs/whatsapp/cloud-api
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Access Token (Bearer)
    |--------------------------------------------------------------------------
    | Token do System User (permanente) ou Token temporário de 24h.
    | Gerar em: Meta for Developers → App → WhatsApp → API Setup
    */
    'access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Phone Number ID
    |--------------------------------------------------------------------------
    | ID do número de telefone registrado no WhatsApp Business.
    | Encontrar em: Meta for Developers → App → WhatsApp → API Setup
    */
    'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business Account ID (WABA ID)
    |--------------------------------------------------------------------------
    */
    'waba_id' => env('META_WHATSAPP_WABA_ID'),

    /*
    |--------------------------------------------------------------------------
    | Verify Token para validação do webhook
    |--------------------------------------------------------------------------
    | Token aleatório definido por você durante a configuração do webhook.
    | Configurar em: Meta for Developers → App → WhatsApp → Configuration → Webhook
    */
    'verify_token' => env('META_WHATSAPP_VERIFY_TOKEN', 'socimob_webhook_verify'),

    /*
    |--------------------------------------------------------------------------
    | Versão da API Graph
    |--------------------------------------------------------------------------
    */
    'api_version' => env('META_WHATSAPP_API_VERSION', 'v18.0'),
];
