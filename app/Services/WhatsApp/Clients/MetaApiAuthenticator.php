<?php

namespace App\Services\WhatsApp\Clients;

use App\Models\WhatsApp\WhatsAppAccount;

class MetaApiAuthenticator
{
    public function accessToken(WhatsAppAccount $account): string
    {
        return (string) ($account->access_token ?: config('whatsapp.graph.default_access_token'));
    }

    public function appSecret(?WhatsAppAccount $account = null): ?string
    {
        return $account?->app_secret ?: config('whatsapp.graph.app_secret');
    }

    public function appId(?WhatsAppAccount $account = null): ?string
    {
        return $account?->app_id ?: config('whatsapp.graph.app_id');
    }

    public function graphVersion(): string
    {
        return (string) config('whatsapp.graph.version', 'v23.0');
    }

    public function verifyToken(): ?string
    {
        return config('whatsapp.graph.webhook_verify_token');
    }
}
