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
        // Webhooks (ex.: Twilio) podem chegar por domínios/hosts que não são tenants.
        // Essas rotas fazem sua própria resolução/validação de tenant no controller.
        $path = trim($request->path(), '/');
        if (str_starts_with($path, 'webhook/')) {
            return $next($request);
        }

        // Obter o dominio da requisicao
        $host = $request->getHost();
        
        // DEBUG LOG
        \Log::info('ResolveTenant middleware start', [
            'host' => $host,
            'path' => $path,
        ]);
        
        $tenant = $this->resolveTenantFromRequest($request);
        if ($tenant) {
            \Log::info('ResolveTenant: Tenant resolved from request', [
                'tenant_id' => $tenant->id,
                'tenant_domain' => $tenant->domain,
            ]);
            
            if (!$tenant->isActive()) {
                return response()->json([
                    'error' => 'Tenant inactive',
                    'message' => 'This tenant is currently inactive.',
                ], 403);
            }

            app()->instance('tenant', $tenant);
            $request->attributes->set('tenant_id', $tenant->id);
            return $next($request);
        }
        
        \Log::info('ResolveTenant: No tenant from request headers', ['host' => $host]);

        // Se for localhost, IP ou ngrok, usar tenant de teste quando configurado
        if ($this->isDevelopment($host) || $this->isNgrok($host)) {
            \Log::info('ResolveTenant: Using development tenant for localhost/ngrok');
            // Em desenvolvimento local, simular exclusivalarimoveis.com para manter multi-tenancy correto
            $tenant = Tenant::byDomain('exclusivalarimoveis.com')->first() ?? Tenant::find(1);
            if ($tenant) {
                app()->instance('tenant', $tenant);
                $request->attributes->set('tenant_id', $tenant->id);
            }
            return $next($request);
        }

        // Mapeamento de domínios alternativos para o domínio principal
        $domainAliases = [
            'lojadaesquina.store' => 'exclusivalarimoveis.com',
            'www.lojadaesquina.store' => 'exclusivalarimoveis.com',
        ];

        // Buscar tenant pelo dominio
        $normalizedHost = $this->normalizeHost($host);
        
        \Log::info('ResolveTenant: Normalized host', ['normalized' => $normalizedHost]);
        
        // Se for um domínio alternativo, usar o domínio principal
        if (isset($domainAliases[$normalizedHost])) {
            \Log::info('ResolveTenant: Found alias mapping', [
                'alias' => $normalizedHost,
                'target' => $domainAliases[$normalizedHost],
            ]);
            $normalizedHost = $domainAliases[$normalizedHost];
        }
        
        $tenant = Tenant::where('domain', $normalizedHost)
            ->orWhere('domain', 'www.' . $normalizedHost)
            ->first();
        
        \Log::info('ResolveTenant: Tenant query result', [
            'normalized_host' => $normalizedHost,
            'tenant_found' => $tenant ? true : false,
            'tenant_id' => $tenant ? $tenant->id : null,
        ]);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found',
                'message' => 'The requested domain is not registered.',
                'host' => $host,
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
        
        \Log::info('ResolveTenant: Tenant set successfully', [
            'tenant_id' => $tenant->id,
            'tenant_domain' => $tenant->domain,
        ]);

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

    private function resolveTenantFromRequest(Request $request): ?Tenant
    {
        // SECURITY: Only accept tenant resolution from headers, NOT query parameters.
        // Query parameters can be easily spoofed by users to access other tenants.
        $tenantId = $request->header('X-Tenant-Id');
        if (!empty($tenantId)) {
            return Tenant::find($tenantId);
        }

        $tenantDomain = $request->header('X-Tenant-Domain');
        if (!empty($tenantDomain)) {
            $normalizedDomain = $this->normalizeHost($tenantDomain);
            return Tenant::where('domain', $normalizedDomain)
                ->orWhere('domain', 'www.' . $normalizedDomain)
                ->first();
        }

        $tenantSlug = $request->header('X-Tenant-Slug');
        if (!empty($tenantSlug)) {
            return Tenant::where('slug', $tenantSlug)->first();
        }

        return null;
    }

    private function normalizeHost(string $host): string
    {
        $normalized = strtolower(trim($host));
        return str_starts_with($normalized, 'www.')
            ? substr($normalized, 4)
            : $normalized;
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
