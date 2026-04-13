<?php

namespace App\Services\WhatsApp\Support;

class SensitiveDataMasker
{
    public function mask(mixed $value): mixed
    {
        $maskKeys = collect(config('whatsapp.logging.mask_keys', []))
            ->map(fn ($item) => strtolower((string) $item))
            ->values()
            ->all();

        return $this->maskValue($value, $maskKeys);
    }

    private function maskValue(mixed $value, array $maskKeys): mixed
    {
        if (is_array($value)) {
            $masked = [];

            foreach ($value as $key => $item) {
                $normalizedKey = strtolower((string) $key);
                $shouldMask = collect($maskKeys)->contains(fn ($maskKey) => str_contains($normalizedKey, $maskKey));
                $masked[$key] = $shouldMask ? $this->maskScalar($item) : $this->maskValue($item, $maskKeys);
            }

            return $masked;
        }

        if (is_object($value)) {
            return $this->maskValue((array) $value, $maskKeys);
        }

        return $value;
    }

    private function maskScalar(mixed $value): string
    {
        $string = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        $length = strlen($string ?? '');

        if ($length <= 8) {
            return str_repeat('*', max($length, 4));
        }

        return substr($string, 0, 4) . str_repeat('*', max($length - 8, 4)) . substr($string, -4);
    }
}
