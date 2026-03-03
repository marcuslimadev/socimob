<?php
namespace App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\Controller;


use App\Models\Tenant;
use App\Services\TenantService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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

        // Para super admin, mostrar campos sensíveis (integrations configs)
        $tenantsWithSecrets = collect($tenants->items())->map(function ($tenant) {
            return $tenant->makeVisible([
                'twilio_auth_token',
                'openai_api_key',
                'mail_password',
                'api_key_pagar_me',
                'api_key_apm_imoveis',
                'api_key_neca',
                'api_key_openai',
                'api_token_externa',
            ]);
        });

        return response()->json([
            'tenants' => $tenantsWithSecrets,
            'total' => $tenants->total(),
            'current_page' => $tenants->currentPage(),
            'per_page' => $tenants->perPage(),
            'last_page' => $tenants->lastPage(),
        ]);
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
            'mascot_url' => 'nullable|string|max:500',
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

    /**
     * Listar associações entre tenants para compartilhamento de portais.
     * GET /api/super-admin/tenant-associations
     */
    public function associationsIndex(Request $request)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenants = Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'domain', 'slug', 'is_active']);

        $associations = [];
        if (DB::getSchemaBuilder()->hasTable('tenant_associations')) {
            $rows = DB::table('tenant_associations')
                ->select('tenant_id', 'associated_tenant_id')
                ->get();

            foreach ($rows as $row) {
                $owner = (int) $row->tenant_id;
                $related = (int) $row->associated_tenant_id;
                if ($owner === $related) {
                    continue;
                }
                if (!isset($associations[$owner])) {
                    $associations[$owner] = [];
                }
                if (!in_array($related, $associations[$owner], true)) {
                    $associations[$owner][] = $related;
                }
            }
        }

        return response()->json([
            'success' => true,
            'tenants' => $tenants,
            'associations' => $associations,
        ]);
    }

    /**
     * Atualizar associações de um tenant.
     * PUT /api/super-admin/tenant-associations/{id}
     */
    public function associationsUpdate(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($id);
        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Validação com melhor tratamento de erro
        $validator = Validator::make($request->all(), [
            'associated_tenant_ids' => 'nullable|array',
            'associated_tenant_ids.*' => 'integer|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            \Log::warning('TenantAssociations validation failed', [
                'tenant_id' => $id,
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Validação falhou',
                'message' => 'Um ou mais tenant IDs são inválidos',
                'messages' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (!DB::getSchemaBuilder()->hasTable('tenant_associations')) {
            return response()->json([
                'success' => false,
                'error' => 'Tabela tenant_associations não encontrada. Execute as migrations.',
            ], 500);
        }

        try {
            $tenantId = (int) $tenant->id;
            $targetIds = collect($validated['associated_tenant_ids'] ?? [])
                ->map(fn ($value) => (int) $value)
                ->filter(fn ($value) => $value > 0 && $value !== $tenantId)
                ->unique()
                ->values()
                ->toArray();

            DB::transaction(function () use ($tenantId, $targetIds, $request) {
                DB::table('tenant_associations')
                    ->where('tenant_id', $tenantId)
                    ->orWhere('associated_tenant_id', $tenantId)
                    ->delete();

                if (empty($targetIds)) {
                    return;
                }

                $now = Carbon::now();
                $userId = $request->user()?->id;
                $rows = [];
                foreach ($targetIds as $targetId) {
                    $rows[] = [
                        'tenant_id' => $tenantId,
                        'associated_tenant_id' => $targetId,
                        'created_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $rows[] = [
                        'tenant_id' => $targetId,
                        'associated_tenant_id' => $tenantId,
                        'created_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('tenant_associations')->insert($rows);
            });

            \Log::info('TenantAssociations updated successfully', [
                'tenant_id' => $tenantId,
                'associated_tenant_ids' => $targetIds,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Associações salvas com sucesso',
                'tenant_id' => $tenantId,
                'associated_tenant_ids' => $targetIds,
            ]);
        } catch (\Exception $e) {
            \Log::error('TenantAssociations update failed', [
                'tenant_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao salvar associações',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
