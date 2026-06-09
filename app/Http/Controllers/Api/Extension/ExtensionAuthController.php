<?php

namespace App\Http\Controllers\Api\Extension;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ExtensionSession;

class ExtensionAuthController extends Controller
{
    public function check(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(["success" => false, "message" => "Não autenticado."], 401);
        }

        // Assuming Tenant and Permissions are accessible via the User model or a service
        $tenant = $user->tenant; // Placeholder for tenant access
        $permissions = $user->getPermissions(); // Placeholder for permissions access
        $moduleConfig = []; // Placeholder for module configurations

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
