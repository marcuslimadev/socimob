<?php

namespace App\Services;

use App\Models\ExtensionSession;
use Illuminate\Support\Facades\Auth;

class ExtensionSessionService
{
    public function updateOrCreateSession(array $data)
    {
        $user = Auth::user();

        return ExtensionSession::updateOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'extension_id' => $data['extension_id'] ?? null,
            ],
            [
                'browser' => $data['browser'] ?? null,
                'last_seen_at' => now(),
                'status' => 'active',
            ]
        );
    }
}
