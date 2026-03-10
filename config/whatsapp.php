<?php

/**
 * Configurações da API WhatsApp Business
 *
 * Suporta dois drivers:
 *  - 'meta'      : Meta WhatsApp Business Cloud API (oficial)
 *  - 'evolution' : Evolution API (self-hosted, baseado em WhatsApp Web)
 *
 * Definir WHATSAPP_DRIVER no .env para escolher o driver.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Driver ativo
    |--------------------------------------------------------------------------
    */
    'driver' => env('WHATSAPP_DRIVER', 'evolution'),

    /*
    |--------------------------------------------------------------------------
    | Evolution API (self-hosted)
    |--------------------------------------------------------------------------
    | URL do servidor Evolution, API Key global e nome da instância.
    | Webhook URL: https://exclusivalarimoveis.com/webhook/whatsapp
    | Verify token não se aplica — Evolution usa apikey no header.
    */
    'evolution' => [
        'url'      => env('EVOLUTION_API_URL', ''),
        'api_key'  => env('EVOLUTION_API_KEY', ''),
        'instance' => env('EVOLUTION_INSTANCE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta WhatsApp Business Cloud API
    |--------------------------------------------------------------------------
    */
    'access_token'    => env('META_WHATSAPP_ACCESS_TOKEN'),
    'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
    'waba_id'         => env('META_WHATSAPP_WABA_ID'),
    'verify_token'    => env('META_WHATSAPP_VERIFY_TOKEN', 'socimob_webhook_verify'),
    'api_version'     => env('META_WHATSAPP_API_VERSION', 'v18.0'),
];
