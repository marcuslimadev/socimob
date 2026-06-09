<?php

namespace App\Services;

use App\Models\ExtensionConsentLog;
use Illuminate\Support\Facades\Auth;

class ConsentService
{
    public function recordConsent(array $data)
    {
        $user = Auth::user();

        return ExtensionConsentLog::create([
            "tenant_id" => $user->tenant_id,
            "user_id" => $user->id,
            "consent_version" => $data["consent_version"],
            "consent_text_hash" => $data["consent_text_hash"],
            "accepted_at" => now(),
            "ip_address" => request()->ip(),
            "user_agent" => request()->userAgent(),
        ]);
    }
}
