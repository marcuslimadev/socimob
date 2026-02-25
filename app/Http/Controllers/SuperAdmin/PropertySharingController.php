<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyPortalTenant;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PropertySharingController extends Controller
{
    /**
     * Validar que o usuário é superadmin
     */
    private function validateSuperAdmin(Request $request)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Apenas superadmin pode gerenciar compartilhamento de imóveis'
            ], 403);
        }
        return null;
    }

    /**
     * Flush cache de portal para tenants afetados
     */
    private function flushPortalCache($tenantIds)
    {
        $ids = is_array($tenantIds) ? $tenantIds : [$tenantIds];
        foreach (array_unique($ids) as $tenantId) {
            Cache::forget("portal_imoveis_tenant_{$tenantId}");
        }
    }

    /**
     * Listar compartilhamentos de imóveis
     * GET /api/super-admin/property-sharing?property_id={id}&tenant_id={id}&owner_tenant_id={id}
     */
    public function index(Request $request)
    {
        $authError = $this->validateSuperAdmin($request);
        if ($authError) return $authError;

        $propertyId = $request->query('property_id');
        $tenantId = $request->query('tenant_id');
        $ownerTenantId = $request->query('owner_tenant_id');

        $query = PropertyPortalTenant::with(['property:id,titulo,codigo,tenant_id', 'property.tenant:id,name,domain', 'tenant:id,name,domain']);

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($ownerTenantId) {
            $query->whereHas('property', function ($q) use ($ownerTenantId) {
                $q->where('tenant_id', $ownerTenantId);
            });
        }

        $sharing = $query->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $sharing->items(),
            'total' => $sharing->total(),
            'current_page' => $sharing->currentPage(),
            'per_page' => $sharing->perPage(),
            'last_page' => $sharing->lastPage(),
        ]);
    }

    /**
     * Obter detalhes de compartilhamento de um imóvel
     * GET /api/super-admin/property-sharing/{propertyId}/details
     */
    public function getPropertySharing(Request $request, $propertyId)
    {
        $authError = $this->validateSuperAdmin($request);
        if ($authError) return $authError;

        $property = Property::find($propertyId);
        if (!$property) {
            return response()->json([
                'error' => 'Property not found',
                'message' => 'Imóvel não encontrado'
            ], 404);
        }

        $ownerTenant = $property->tenant;
        $sharedWith = PropertyPortalTenant::where('property_id', $propertyId)
            ->with('tenant:id,name,domain,slug')
            ->get();

        return response()->json([
            'success' => true,
            'property' => [
                'id' => $property->id,
                'titulo' => $property->titulo,
                'codigo' => $property->codigo,
                'owner_tenant_id' => $property->tenant_id,
            ],
            'owner_tenant' => $ownerTenant ? [
                'id' => $ownerTenant->id,
                'name' => $ownerTenant->name,
                'domain' => $ownerTenant->domain,
            ] : null,
            'shared_with' => $sharedWith->map(fn ($s) => [
                'id' => $s->id,
                'tenant_id' => $s->tenant_id,
                'tenant' => [
                    'id' => $s->tenant->id,
                    'name' => $s->tenant->name,
                    'domain' => $s->tenant->domain,
                    'slug' => $s->tenant->slug,
                ],
                'created_at' => $s->created_at,
            ])->toArray(),
        ]);
    }
}
