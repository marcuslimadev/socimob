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
        // Obter o dominio da requisicao
        $host = $request->getHost();

        // Se for localhost, IP ou ngrok, usar tenant de teste quando configurado
        if ($this->isDevelopment($host) || $this->isNgrok($host)) {
            $tenant = $this->resolveWebhookTenant($request) ?? Tenant::first();
            if ($tenant) {
                app()->instance('tenant', $tenant);
                $request->attributes->set('tenant_id', $tenant->id);
            }
            return $next($request);
        }

        // Buscar tenant pelo dominio
        $tenant = Tenant::byDomain($host)->first();

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found',
                'message' => 'The requested domain is not registered.',
            ], 404);
        }

        // Verificar se o tenant esta ativo
        if (!$tenant->isActive()) {
            return response()->json([
                'error' => 'Tenant inactive',
                'message' => 'This tenant is currently inactive.',
            ], 403);
        }

        // Registrar tenant no container
        app()->instance('tenant', $tenant);

        // Adicionar tenant_id a requisicao
        $request->attributes->set('tenant_id', $tenant->id);

        return $next($request);
    }

    /**
     * Verificar se esta em desenvolvimento.
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

    private function isNgrok(string $host): bool
    {
        return str_ends_with($host, '.ngrok-free.app') || str_ends_with($host, '.ngrok.io');
    }

    private function resolveWebhookTenant(Request $request): ?Tenant
    {
        if (!str_starts_with(trim($request->path(), '/'), 'webhook')) {
            return null;
        }

        $tenantId = env('WEBHOOK_TENANT_ID');
        if (!empty($tenantId)) {
            return Tenant::find($tenantId);
        }

        $tenantDomain = env('WEBHOOK_TENANT_DOMAIN');
        if (!empty($tenantDomain)) {
            return Tenant::byDomain($tenantDomain)->first();
        }

        $tenantSlug = env('WEBHOOK_TENANT_SLUG');
        if (!empty($tenantSlug)) {
            return Tenant::where('slug', $tenantSlug)->first();
        }

        return null;
    }
}
