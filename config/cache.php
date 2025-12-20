<?php

return [
    'default' => env('CACHE_DRIVER', 'database'),
    
    'stores' => [
        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
        ],
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
    ],
    
    'prefix' => env('CACHE_PREFIX', 'exclusiva_cache'),
];
