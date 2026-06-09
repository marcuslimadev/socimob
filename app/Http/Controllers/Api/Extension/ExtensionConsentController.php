<?php

namespace App\Http\Controllers\Api\Extension;

use App\Http\Controllers\Controller;
use App\Models\ExtensionConsentLog;
use Illuminate\Http\Request;

class ExtensionConsentController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            "consent_version" => "required|string",
            "consent_text_hash" => "required|string",
        ]);

        $consentLog = ExtensionConsentLog::create([
            "tenant_id" => $user->tenant_id,
            "user_id" => $user->id,
            "consent_version" => $request->input("consent_version"),
            "consent_text_hash" => $request->input("consent_text_hash"),
            "accepted_at" => now(),
            "ip_address" => $request->ip(),
            "user_agent" => $request->userAgent(),
        ]);

        return response()->json(["success" => true, "data" => $consentLog, "message" => "Consentimento registrado com sucesso."]);
    }
}
