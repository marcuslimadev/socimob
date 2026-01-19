<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Listar todos os tenants
     * GET /api/super-admin/tenants
     */
    public function index(Request $request)
    {
        // Require super admin
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $perPage = $request->query('per_page', 15);
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Tenant::query();

        // Filtrar por busca
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        // Filtrar por status
        if ($status) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'subscribed') {
                $query->where('subscription_status', 'active');
            }
        }

        $tenants = $query->paginate($perPage);

        return response()->json($tenants);
    }

    /**
     * Obter detalhes de um tenant
     * GET /api/super-admin/tenants/{id}
     */
    public function show(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::with(['users', 'subscription', 'config'])->find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Adicionar estatísticas
        $stats = $this->tenantService->getStats($tenant);

        return response()->json([
            'tenant' => $tenant,
            'stats' => $stats,
        ]);
    }

    /**
     * Criar novo tenant
     * POST /api/super-admin/tenants
     */
    public function store(Request $request)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $this->validate($request, [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants,domain',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'theme' => 'nullable|in:classico,bauhaus',
            'max_users' => 'nullable|integer|min:1|max:1000',
            'max_properties' => 'nullable|integer|min:1|max:10000',
            'max_leads' => 'nullable|integer|min:1|max:50000',
            'admin_password' => 'required|string|min:6',
            'admin_role' => 'nullable|in:admin,user',
        ]);

        try {
            $tenant = $this->tenantService->create($validated);

            return response()->json([
                'message' => 'Tenant created successfully',
                'tenant' => $tenant,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create tenant',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualizar tenant
     * PUT /api/super-admin/tenants/{id}
     */
    public function update(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $validated = $this->validate($request, [
            'name' => 'nullable|string|max:255',
            'domain' => 'nullable|string|max:255|unique:tenants,domain,' . $id,
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'theme' => 'nullable|in:classico,bauhaus',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'logo_url' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'admin_password' => 'nullable|string|min:6',
            'admin_role' => 'nullable|in:admin,user',
            'max_users' => 'nullable|integer|min:1|max:1000',
            'max_properties' => 'nullable|integer|min:1|max:10000',
            'max_leads' => 'nullable|integer|min:1|max:50000',
        ]);

        try {
            $tenant = $this->tenantService->update($tenant, $validated);

            return response()->json([
                'message' => 'Tenant updated successfully',
                'tenant' => $tenant,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update tenant',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deletar tenant
     * DELETE /api/super-admin/tenants/{id}
     */
    public function destroy(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        try {
            $this->tenantService->delete($tenant);

            return response()->json([
                'message' => 'Tenant deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete tenant',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ativar tenant
     * POST /api/super-admin/tenants/{id}/activate
     */
    public function activate(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $this->tenantService->activate($tenant);

        return response()->json([
            'message' => 'Tenant activated successfully',
            'tenant' => $tenant,
        ]);
    }

    /**
     * Desativar tenant
     * POST /api/super-admin/tenants/{id}/deactivate
     */
    public function deactivate(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $this->tenantService->deactivate($tenant);

        return response()->json([
            'message' => 'Tenant deactivated successfully',
            'tenant' => $tenant,
        ]);
    }

    /**
     * Gerar novo token de API
     * POST /api/super-admin/tenants/{id}/generate-api-token
     */
    public function generateApiToken(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $token = $tenant->generateApiToken();

        return response()->json([
            'message' => 'API token generated successfully',
            'api_token' => $token,
        ]);
    }

    /**
     * Obter estatísticas de um tenant
     * GET /api/super-admin/tenants/{id}/stats
     */
    public function stats(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $stats = $this->tenantService->getStats($tenant);

        return response()->json($stats);
    }

    /**
     * Listar usuários de um tenant
     * GET /api/super-admin/tenants/{id}/users
     */
    public function users(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $users = $tenant->users()->paginate(15);

        return response()->json($users);
    }

    /**
     * Suspender assinatura
     * POST /api/super-admin/tenants/{id}/suspend-subscription
     */
    public function suspendSubscription(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $reason = $request->input('reason');

        $this->tenantService->suspendSubscription($tenant, $reason);

        return response()->json([
            'message' => 'Subscription suspended successfully',
            'tenant' => $tenant,
        ]);
    }

    /**
     * Ativar assinatura
     * POST /api/super-admin/tenants/{id}/activate-subscription
     */
    public function activateSubscription(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $this->tenantService->activateSubscription($tenant);

        return response()->json([
            'message' => 'Subscription activated successfully',
            'tenant' => $tenant,
        ]);
    }
}
