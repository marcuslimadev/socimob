<?php

return [
    'driver' => env('WHATSAPP_DRIVER', 'meta_cloud'),
    'integration_version' => env('WHATSAPP_INTEGRATION_VERSION', '2026-04-13'),
    'default_tenant_route_binding' => env('WHATSAPP_DEFAULT_TENANT_BINDING', 'phone_number_id'),
    'graph' => [
        'base_url' => env('META_GRAPH_BASE_URL', 'https://graph.facebook.com'),
        'version' => env('META_GRAPH_API_VERSION', env('META_WHATSAPP_API_VERSION', 'v23.0')),
        'app_id' => env('META_APP_ID', env('META_WHATSAPP_APP_ID')),
        'app_secret' => env('META_APP_SECRET', env('META_WHATSAPP_APP_SECRET')),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', env('META_WHATSAPP_VERIFY_TOKEN')),
        'timeout_seconds' => (int) env('META_GRAPH_TIMEOUT_SECONDS', 30),
        'connect_timeout_seconds' => (int) env('META_GRAPH_CONNECT_TIMEOUT_SECONDS', 10),
        'retry_attempts' => (int) env('META_GRAPH_RETRY_ATTEMPTS', 3),
        'retry_backoff_ms' => (int) env('META_GRAPH_RETRY_BACKOFF_MS', 500),
        'rate_limit_backoff_ms' => (int) env('META_GRAPH_RATE_LIMIT_BACKOFF_MS', 1500),
        'default_access_token' => env('META_GRAPH_ACCESS_TOKEN', env('META_WHATSAPP_ACCESS_TOKEN')),
        'default_waba_id' => env('META_WABA_ID', env('META_WHATSAPP_WABA_ID')),
        'default_business_account_id' => env('META_BUSINESS_ACCOUNT_ID'),
        'default_phone_number_id' => env('META_PHONE_NUMBER_ID', env('META_WHATSAPP_PHONE_NUMBER_ID')),
    ],
    'webhook' => [
        'signature_header' => 'X-Hub-Signature-256',
        'signature_prefix' => 'sha256=',
        'max_batch_size' => 1000,
        'dedup_ttl_hours' => (int) env('META_WEBHOOK_DEDUP_TTL_HOURS', 72),
        'queue' => env('WHATSAPP_WEBHOOK_QUEUE', 'whatsapp-webhooks'),
        'process_inbound_sync' => filter_var(env('WHATSAPP_PROCESS_INBOUND_SYNC', true), FILTER_VALIDATE_BOOL),
    ],
    'queue' => [
        'outbound' => env('WHATSAPP_OUTBOUND_QUEUE', 'whatsapp-outbound'),
        'webhook' => env('WHATSAPP_WEBHOOK_QUEUE', 'whatsapp-webhooks'),
        'templates' => env('WHATSAPP_TEMPLATE_QUEUE', 'whatsapp-templates'),
    ],
    'rate_limits' => [
        'per_tenant_per_minute' => (int) env('WHATSAPP_RATE_LIMIT_PER_TENANT_PER_MINUTE', 300),
        'per_contact_per_minute' => (int) env('WHATSAPP_RATE_LIMIT_PER_CONTACT_PER_MINUTE', 20),
    ],
    'storage' => [
        'disk' => env('WHATSAPP_MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),
        'path_prefix' => env('WHATSAPP_MEDIA_PATH_PREFIX', 'whatsapp'),
    ],
    'logging' => [
        'channel' => env('WHATSAPP_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'mask_keys' => [
            'access_token',
            'authorization',
            'app_secret',
            'token',
            'bearer',
        ],
        'persist_http_payloads' => filter_var(env('WHATSAPP_PERSIST_HTTP_PAYLOADS', true), FILTER_VALIDATE_BOOL),
    ],
];
