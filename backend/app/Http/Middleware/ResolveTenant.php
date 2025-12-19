<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class ResolveTenant
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
        // Obter o domínio da requisição
        $host = $request->getHost();

        // Se for localhost ou IP, pular (para desenvolvimento)
        if ($this->isDevelopment($host)) {
            // Em desenvolvimento, usar o primeiro tenant ou criar um padrão
            $tenant = Tenant::first();
            if ($tenant) {
                app()->instance('tenant', $tenant);
                $request->attributes->set('tenant_id', $tenant->id);
            }
            return $next($request);
        }

        // Buscar tenant pelo domínio
        $tenant = Tenant::byDomain($host)->first();

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found',
                'message' => 'The requested domain is not registered.',
            ], 404);
        }

        // Verificar se o tenant está ativo
        if (!$tenant->isActive()) {
            return response()->json([
                'error' => 'Tenant inactive',
                'message' => 'This tenant is currently inactive.',
            ], 403);
        }

        // Registrar tenant no container
        app()->instance('tenant', $tenant);
        
        // Adicionar tenant_id à requisição
        $request->attributes->set('tenant_id', $tenant->id);

        return $next($request);
    }

    /**
     * Verificar se está em desenvolvimento
     *
     * @param  string  $host
     * @return bool
     */
    private function isDevelopment(string $host): bool
    {
        return in_array($host, [
            'localhost',
            '127.0.0.1',
            '::1',
        ]) || str_ends_with($host, '.local');
    }
}
