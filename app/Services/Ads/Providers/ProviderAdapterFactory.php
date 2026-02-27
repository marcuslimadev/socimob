<?php

namespace App\Services\Ads\Providers;

use App\Services\Ads\TokenEncryptionService;
use RuntimeException;

/**
 * Resolve o adapter correto por nome de provider.
 * Registre novos providers aqui.
 */
class ProviderAdapterFactory
{
    public function __construct(
        private TokenEncryptionService $enc,
    ) {}

    public function make(string $provider): ProviderAdapterInterface
    {
        return match ($provider) {
            'meta'   => new MetaAdapter($this->enc),
            'google' => new GoogleAdapter(),
            'olx'    => new OlxAdapter($this->enc),
            default  => throw new RuntimeException("Provider desconhecido: '{$provider}'"),
        };
    }
}
