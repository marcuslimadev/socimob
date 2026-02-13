<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SimpleTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || stripos($authHeader, 'bearer ') !== 0) {
            Log::warning('SimpleTokenAuth: Missing or invalid Authorization header');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = trim(substr($authHeader, 7));
        $decoded = base64_decode($token, true);
        if (!$decoded || !str_contains($decoded, '|')) {
            Log::warning('SimpleTokenAuth: Invalid token format', ['token' => substr($token, 0, 20)]);
            return response()->json(['error' => 'Invalid token'], 401);
        }

        [$userId] = explode('|', $decoded, 3);
        $user = User::with('tenant')->find($userId);

        if (!$user) {
            Log::warning('SimpleTokenAuth: User not found', ['userId' => $userId]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Inject user in request for controllers
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // TENANT ISOLATION: Enforce user belongs to the resolved tenant
        // Read the tenant_id that ResolveTenant middleware may have set from the domain
        $resolvedTenantId = $request->attributes->get('tenant_id');

        // Super admins can access any tenant - keep the resolved tenant context
        if ($user->role === 'super_admin') {
            if ($resolvedTenantId && app()->bound('tenant')) {
                // Keep the domain-resolved tenant for super admin
                // tenant already set by ResolveTenant
            } elseif ($user->tenant) {
                app()->instance('tenant', $user->tenant);
                $request->attributes->set('tenant_id', $user->tenant_id);
            }
        } elseif ($user->tenant_id) {
            // CRITICAL: Regular users MUST match the domain's tenant
            // If a tenant was resolved from the domain, verify the user belongs to it
            if ($resolvedTenantId && $resolvedTenantId != $user->tenant_id) {
                Log::warning('SimpleTokenAuth: Tenant mismatch - BLOCKED', [
                    'userId' => $user->id,
                    'userName' => $user->name,
                    'userEmail' => $user->email,
                    'user_tenant_id' => $user->tenant_id,
                    'resolved_tenant_id' => $resolvedTenantId,
                    'domain' => $request->getHost(),
                ]);
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Access denied. You do not have permission to access this tenant. Please contact your administrator.'
                ], 403);
            }

            // CRITICAL: DO NOT OVERRIDE the tenant set by ResolveTenant
            // The domain MUST be the source of truth, not the user's tenant_id
            // Only set tenant if it wasn't already set by ResolveTenant
            if (!$resolvedTenantId && $user->tenant) {
                app()->instance('tenant', $user->tenant);
                $request->attributes->set('tenant_id', $user->tenant_id);
            }
        } else {
            // Non-super-admin user without tenant_id should not have access
            Log::warning('SimpleTokenAuth: User without tenant_id is not super_admin - BLOCKED', [
                'userId' => $user->id,
                'role' => $user->role,
            ]);
            return response()->json(['error' => 'Unauthorized - no tenant assigned'], 403);
        }

        return $next($request);
    }
}
