<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateTenantAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Se não houver um tenant resolvido, retornar erro
        if (!app()->bound('tenant') || !$request->attributes->get('tenant_id')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'No tenant context found.',
            ], 401);
        }

        // Se o usuário estiver autenticado, verificar se pertence ao tenant
        if ($request->user()) {
            $tenantId = $request->attributes->get('tenant_id');
            $userTenantId = $request->user()->tenant_id;

            // Super admin pode acessar qualquer tenant
            if ($request->user()->isSuperAdmin()) {
                return $next($request);
            }

            // Usuário normal deve pertencer ao tenant
            if ($userTenantId !== $tenantId) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'You do not have access to this tenant.',
                ], 403);
            }
        }

        return $next($request);
    }
}
