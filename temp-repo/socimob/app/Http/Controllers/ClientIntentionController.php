<?php

namespace App\Http\Controllers;

use App\Models\ClientIntention;
use App\Models\Tenant;
use App\Services\IntentionService;
use Illuminate\Http\Request;

class ClientIntentionController extends Controller
{
    protected $intentionService;

    public function __construct(IntentionService $intentionService)
    {
        $this->intentionService = $intentionService;
    }

    /**
     * Listar intenções do cliente
     * GET /api/intentions
     */
    public function index(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $status = $request->query('status');
        $type = $request->query('type');
        $perPage = $request->query('per_page', 15);

        $query = ClientIntention::forTenant($tenantId);

        if ($status) {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('type', $type);
        }

        // Se for cliente, mostrar apenas suas intenções
        if ($request->user() && $request->user()->isCliente()) {
            $query->where('client_id', $request->user()->id);
        }

        $intentions = $query->paginate($perPage);

        return response()->json($intentions);
    }

    /**
     * Obter detalhes de uma intenção
     * GET /api/intentions/{id}
     */
    public function show(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $intention = ClientIntention::forTenant($tenantId)->find($id);

        if (!$intention) {
            return response()->json(['error' => 'Intention not found'], 404);
        }

        // Se for cliente, verificar se é sua intenção
        if ($request->user() && $request->user()->isCliente()) {
            if ($intention->client_id !== $request->user()->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $stats = $this->intentionService->getStats($intention);

        return response()->json([
            'intention' => $intention,
            'stats' => $stats,
        ]);
    }

    /**
     * Criar nova intenção
     * POST /api/intentions
     */
    public function store(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'type' => 'required|in:venda,aluguel',
            'min_bedrooms' => 'nullable|integer|min:1',
            'max_bedrooms' => 'nullable|integer|min:1',
            'min_bathrooms' => 'nullable|integer|min:1',
            'max_bathrooms' => 'nullable|integer|min:1',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'min_area' => 'nullable|integer|min:1',
            'max_area' => 'nullable|integer|min:1',
            'city' => 'nullable|string|max:100',
            'neighborhoods' => 'nullable|array',
            'neighborhoods.*' => 'string|max:100',
            'features' => 'nullable|array',
            'features.*' => 'string|max:100',
            'observations' => 'nullable|string|max:1000',
            'notify_by_email' => 'nullable|boolean',
            'notify_by_whatsapp' => 'nullable|boolean',
            'notify_by_sms' => 'nullable|boolean',
        ]);

        try {
            $tenant = Tenant::find($tenantId);

            // Se usuário está autenticado, vincular à conta
            if ($request->user()) {
                $validated['client_id'] = $request->user()->id;
            }

            $intention = $this->intentionService->create($tenant, $validated);

            return response()->json([
                'message' => 'Intention created successfully',
                'intention' => $intention,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create intention',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualizar intenção
     * PUT /api/intentions/{id}
     */
    public function update(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $intention = ClientIntention::forTenant($tenantId)->find($id);

        if (!$intention) {
            return response()->json(['error' => 'Intention not found'], 404);
        }

        // Se for cliente, verificar se é sua intenção
        if ($request->user() && $request->user()->isCliente()) {
            if ($intention->client_id !== $request->user()->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'type' => 'nullable|in:venda,aluguel',
            'min_bedrooms' => 'nullable|integer|min:1',
            'max_bedrooms' => 'nullable|integer|min:1',
            'min_bathrooms' => 'nullable|integer|min:1',
            'max_bathrooms' => 'nullable|integer|min:1',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'min_area' => 'nullable|integer|min:1',
            'max_area' => 'nullable|integer|min:1',
            'city' => 'nullable|string|max:100',
            'neighborhoods' => 'nullable|array',
            'neighborhoods.*' => 'string|max:100',
            'features' => 'nullable|array',
            'features.*' => 'string|max:100',
            'observations' => 'nullable|string|max:1000',
            'notify_by_email' => 'nullable|boolean',
            'notify_by_whatsapp' => 'nullable|boolean',
            'notify_by_sms' => 'nullable|boolean',
        ]);

        try {
            $intention = $this->intentionService->update($intention, $validated);

            return response()->json([
                'message' => 'Intention updated successfully',
                'intention' => $intention,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update intention',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deletar intenção
     * DELETE /api/intentions/{id}
     */
    public function destroy(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $intention = ClientIntention::forTenant($tenantId)->find($id);

        if (!$intention) {
            return response()->json(['error' => 'Intention not found'], 404);
        }

        // Se for cliente, verificar se é sua intenção
        if ($request->user() && $request->user()->isCliente()) {
            if ($intention->client_id !== $request->user()->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        try {
            $this->intentionService->delete($intention);

            return response()->json([
                'message' => 'Intention deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete intention',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pausar intenção
     * POST /api/intentions/{id}/pause
     */
    public function pause(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $intention = ClientIntention::forTenant($tenantId)->find($id);

        if (!$intention) {
            return response()->json(['error' => 'Intention not found'], 404);
        }

        // Se for cliente, verificar se é sua intenção
        if ($request->user() && $request->user()->isCliente()) {
            if ($intention->client_id !== $request->user()->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $intention = $this->intentionService->pause($intention);

        return response()->json([
            'message' => 'Intention paused successfully',
            'intention' => $intention,
        ]);
    }

    /**
     * Retomar intenção
     * POST /api/intentions/{id}/resume
     */
    public function resume(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $intention = ClientIntention::forTenant($tenantId)->find($id);

        if (!$intention) {
            return response()->json(['error' => 'Intention not found'], 404);
        }

        // Se for cliente, verificar se é sua intenção
        if ($request->user() && $request->user()->isCliente()) {
            if ($intention->client_id !== $request->user()->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $intention = $this->intentionService->resume($intention);

        return response()->json([
            'message' => 'Intention resumed successfully',
            'intention' => $intention,
        ]);
    }

    /**
     * Obter imóveis que combinam
     * GET /api/intentions/{id}/matches
     */
    public function matches(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $intention = ClientIntention::forTenant($tenantId)->find($id);

        if (!$intention) {
            return response()->json(['error' => 'Intention not found'], 404);
        }

        $properties = $this->intentionService->findMatchingProperties($intention);

        return response()->json([
            'intention_id' => $intention->id,
            'matching_properties_count' => count($properties),
            'properties' => $properties,
        ]);
    }

    /**
     * Obter notificações da intenção
     * GET /api/intentions/{id}/notifications
     */
    public function notifications(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $intention = ClientIntention::forTenant($tenantId)->find($id);

        if (!$intention) {
            return response()->json(['error' => 'Intention not found'], 404);
        }

        // Se for cliente, verificar se é sua intenção
        if ($request->user() && $request->user()->isCliente()) {
            if ($intention->client_id !== $request->user()->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $perPage = $request->query('per_page', 15);
        $notifications = $intention->notifications()
            ->latest()
            ->paginate($perPage);

        return response()->json($notifications);
    }
}
