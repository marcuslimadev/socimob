<?php

namespace App\Services\WhatsApp\Support;

class MetaWebhookSignatureValidator
{
    public function isValid(string $payload, ?string $signatureHeader, ?string $appSecret): bool
    {
        if (!$appSecret || !$signatureHeader) {
            return false;
        }

        $prefix = (string) config('whatsapp.webhook.signature_prefix', 'sha256=');
        $signature = str_starts_with($signatureHeader, $prefix)
            ? substr($signatureHeader, strlen($prefix))
            : $signatureHeader;

        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $appSecret);

        return hash_equals($expected, $signature);
    }
}
