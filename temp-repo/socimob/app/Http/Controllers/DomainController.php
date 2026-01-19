<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\DomainService;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    protected $domainService;

    public function __construct(DomainService $domainService)
    {
        $this->domainService = $domainService;
    }

    /**
     * Obter domínio atual do tenant
     * GET /api/domain
     */
    public function current(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        return response()->json([
            'domain' => $tenant->domain,
            'url' => $this->domainService->getTenantUrl($tenant),
            'api_url' => $this->domainService->getTenantApiUrl($tenant),
        ]);
    }

    /**
     * Atualizar domínio do tenant
     * PUT /api/domain
     */
    public function update(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        // Verificar se é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $validated = $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        try {
            $tenant = $this->domainService->updateDomain($tenant, $validated['domain']);

            return response()->json([
                'message' => 'Domain updated successfully',
                'domain' => $tenant->domain,
                'url' => $this->domainService->getTenantUrl($tenant),
                'api_url' => $this->domainService->getTenantApiUrl($tenant),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update domain',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validar domínio
     * POST /api/domain/validate
     */
    public function validate(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        $domain = $validated['domain'];

        $isValid = $this->domainService->validateDomain($domain);
        $isAvailable = !$this->domainService->findByDomain($domain);

        return response()->json([
            'domain' => $domain,
            'is_valid' => $isValid,
            'is_available' => $isAvailable,
            'message' => $this->getValidationMessage($isValid, $isAvailable),
        ]);
    }

    /**
     * Obter DNS info
     * GET /api/domain/dns
     */
    public function dnsInfo(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        // Verificar se é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        try {
            $dnsInfo = $this->domainService->getDNSInfo($tenant->domain);

            return response()->json($dnsInfo);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get DNS info',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Obter instruções de DNS
     * GET /api/domain/dns-instructions
     */
    public function dnsInstructions(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        // Verificar se é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $instructions = $this->domainService->generateDNSInstructions($tenant);

        return response()->json($instructions);
    }

    /**
     * Listar domínios alternativos
     * GET /api/domain/alternatives
     */
    public function alternatives(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $domains = $this->domainService->getAlternativeDomains($tenant);

        return response()->json([
            'domains' => $domains,
        ]);
    }

    /**
     * Gerar domínio sugerido
     * POST /api/domain/suggest
     */
    public function suggest(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $suggestedDomain = $this->domainService->generateSuggestedDomain($validated['name']);

            return response()->json([
                'suggested_domain' => $suggestedDomain,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate suggested domain',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Obter mensagem de validação
     */
    private function getValidationMessage(bool $isValid, bool $isAvailable): string
    {
        if (!$isValid) {
            return 'Domínio inválido. Use um formato válido como: exemplo.com.br';
        }

        if (!$isAvailable) {
            return 'Domínio já está em uso.';
        }

        return 'Domínio válido e disponível.';
    }
}
