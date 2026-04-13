<?php

namespace App\Services\WhatsApp\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CorrelationId
{
    public static function fromRequest(?Request $request = null): string
    {
        $incoming = $request?->header('X-Correlation-Id');

        if (is_string($incoming) && trim($incoming) !== '') {
            return trim($incoming);
        }

        return (string) Str::uuid();
    }
}
