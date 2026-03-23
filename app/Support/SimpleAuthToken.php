<?php

namespace App\Support;

use RuntimeException;

final class SimpleAuthToken
{
    private const VERSION = 1;

    public static function issue(int $userId): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + self::ttl();

        $payload = [
            'v' => self::VERSION,
            'uid' => $userId,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'rnd' => bin2hex(random_bytes(16)),
        ];

        $encodedPayload = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = self::base64UrlEncode(hash_hmac('sha256', $encodedPayload, self::signingKey(), true));

        return $encodedPayload . '.' . $signature;
    }

    public static function decode(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || !str_contains($token, '.')) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = explode('.', $token, 2);
        if ($encodedPayload === '' || $encodedSignature === '') {
            return null;
        }

        $expectedSignature = self::base64UrlEncode(hash_hmac('sha256', $encodedPayload, self::signingKey(), true));
        if (!hash_equals($expectedSignature, $encodedSignature)) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($encodedPayload);
        if ($payloadJson === null) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        $userId = $payload['uid'] ?? null;
        $issuedAt = $payload['iat'] ?? null;
        $expiresAt = $payload['exp'] ?? null;
        $version = $payload['v'] ?? null;

        if (!is_int($userId) || $userId <= 0) {
            return null;
        }

        if (!is_int($issuedAt) || !is_int($expiresAt) || $expiresAt < $issuedAt) {
            return null;
        }

        if ($version !== self::VERSION || $expiresAt < time()) {
            return null;
        }

        return [
            'user_id' => $userId,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ];
    }

    private static function ttl(): int
    {
        return max(300, (int) config('auth.api_token_ttl', 86400));
    }

    private static function signingKey(): string
    {
        $configuredKey = (string) (config('app.key') ?: env('JWT_SECRET', ''));
        if ($configuredKey === '') {
            throw new RuntimeException('APP_KEY ou JWT_SECRET precisa estar configurado para emitir tokens.');
        }

        if (str_starts_with($configuredKey, 'base64:')) {
            $decoded = base64_decode(substr($configuredKey, 7), true);
            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        return $configuredKey;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}