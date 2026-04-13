<?php

namespace App\Services\WhatsApp\Support;

class WhatsAppPhoneNormalizer
{
    public static function normalize(string $phone, string $defaultCountryCode = '55'): string
    {
        $trimmed = trim($phone);
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return $trimmed;
        }

        if (!str_starts_with($digits, $defaultCountryCode) && strlen($digits) <= 11) {
            $digits = $defaultCountryCode . $digits;
        }

        return $digits;
    }
}
