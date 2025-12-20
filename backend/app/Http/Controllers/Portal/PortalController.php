<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Property;
use App\Services\PropertyLikesTablesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalController extends Controller
{
    /**
     * Retorna configurações do tenant para o portal público
     * GET /api/portal/config
     */
    public function getConfig(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $config = $tenant->config;
        $portalFinalidades = null;
        if ($config && is_array($config->portal_finalidades)) {
            $portalFinalidades = $config->portal_finalidades;
        }

        return response()->json([
            'tenant' => [
                'name' => $tenant->name,
                'contact_phone' => $tenant->contact_phone,
                'contact_email' => $tenant->contact_email,
                'domain' => $tenant->domain,
                'slogan' => $tenant->slogan ?? 'Encontre o Imovel dos Seus Sonhos',
                'primary_color' => $tenant->primary_color ?? '#1e293b',
                'secondary_color' => $tenant->secondary_color ?? '#3b82f6',
                'logo_url' => $tenant->logo_url,
                'favicon_url' => $tenant->favicon_url,
                'portal_finalidades' => $portalFinalidades,
            ]
        ]);
    }

    /**
     * Lista imoveis disponiveis do tenant (publico)
     * GET /api/portal/imoveis
     */
    public function getImoveis(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        PropertyLikesTablesManager::ensurePropertyLikesTableExists();
        $tenant = Tenant::find($tenantId);
        $config = $tenant ? $tenant->config : null;
        $allowedFinalidades = $config && is_array($config->portal_finalidades)
            ? $config->portal_finalidades
            : null;

        $tenant = Tenant::find($tenantId);
        $config = $tenant ? $tenant->config : null;
        $allowedFinalidades = $config && is_array($config->portal_finalidades)
            ? $config->portal_finalidades
            : null;

        if (is_array($allowedFinalidades) && count($allowedFinalidades) === 0) {
            return response()->json([
                'success' => true,
                'data' => [],
                'total' => 0
            ]);
        }

        $imoveisQuery = Property::where('tenant_id', $tenantId)
            ->where('active', true)
            ->where('exibir_imovel', true)
            ->orderBy('created_at', 'desc');

        if (is_array($allowedFinalidades) && count($allowedFinalidades) > 0) {
            $imoveisQuery->whereIn('finalidade_imovel', $allowedFinalidades);
        }

        $imoveis = $imoveisQuery->get();

        $likesMap = DB::table('property_likes')
            ->select('property_id', DB::raw('COUNT(*) as total'))
            ->where('tenant_id', $tenantId)
            ->groupBy('property_id')
            ->pluck('total', 'property_id');

        $imoveis = $imoveis->map(function ($imovel) use ($likesMap) {
            $imovel->likes_count = (int) ($likesMap[$imovel->id] ?? 0);
            return $imovel;
        });

        return response()->json([
            'success' => true,
            'data' => $imoveis,
            'total' => $imoveis->count()
        ]);
    }

    /**
     * Detalhes de um imovel especifico (publico)
     * GET /api/portal/imoveis/{id}
     */
    public function getImovel(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');
        
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        PropertyLikesTablesManager::ensurePropertyLikesTableExists();

        $imovel = Property::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('active', true)
            ->where('exibir_imovel', true)
            ->first();

        if (!$imovel) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        if (is_array($allowedFinalidades) && count($allowedFinalidades) > 0) {
            if (!in_array($imovel->finalidade_imovel, $allowedFinalidades, true)) {
                return response()->json(['error' => 'Property not found'], 404);
            }
        }

        $likesCount = DB::table('property_likes')
            ->where('tenant_id', $tenantId)
            ->where('property_id', $imovel->id)
            ->count();

        $imovel->likes_count = (int) $likesCount;

        return response()->json([
            'success' => true,
            'data' => $imovel
        ]);
    }

    /**
     * Registrar interesse/lead (público)
     * POST /api/portal/interesse
     */
    public function registrarInteresse(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');
        
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $this->validate($request, [
            'property_id' => 'required|exists:properties,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'message' => 'nullable|string|max:1000'
        ]);

        // Criar lead (você pode criar uma tabela 'leads' depois)
        // Por enquanto, retornar sucesso
        
        return response()->json([
            'success' => true,
            'message' => 'Interesse registrado com sucesso! Entraremos em contato em breve.'
        ]);
    }
}




