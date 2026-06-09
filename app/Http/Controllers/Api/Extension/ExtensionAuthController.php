<?php

namespace App\Http\Controllers\Api\Extension;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExtensionSession;

class ExtensionAuthController extends Controller
{
    public function check(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(["success" => false, "message" => "Não autenticado."], 401);
        }

        $tenant = $user->tenant;
        $permissions = [$user->role];
        $moduleConfig = [];

        // Optionally, update extension session last seen
        ExtensionSession::updateOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'extension_id' => $request->header('X-Extension-ID'), // Assuming extension sends its ID
            ],
            [
                'browser' => $request->header('User-Agent'),
                'last_seen_at' => now(),
                'status' => 'active',
            ]
        );

        return response()->json([
            "success" => true,
            "data" => [
                "user" => $user,
                "tenant" => $tenant,
                "permissions" => $permissions,
                "module_config" => $moduleConfig,
            ],
            "message" => "Token validado com sucesso."
        ]);
    }
}
