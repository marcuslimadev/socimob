<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Property;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    /**
     * Retorna configurações do tenant para o portal público
     * GET /api/portal/config
     */
    public function getConfig(Request $request)
    {
        // Tenant já foi resolvido pelo middleware ResolveTenant
        $tenantId = $request->attributes->get('tenant_id');
        
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Retornar apenas dados públicos
        return response()->json([
            'tenant' => [
                'name' => $tenant->name,
                'contact_phone' => $tenant->contact_phone,
                'contact_email' => $tenant->contact_email,
                'domain' => $tenant->domain,
                'slogan' => $tenant->slogan ?? 'Encontre o Imóvel dos Seus Sonhos',
                'primary_color' => $tenant->primary_color ?? '#1e293b',
                'secondary_color' => $tenant->secondary_color ?? '#3b82f6',
                'logo_url' => $tenant->logo_url,
                'favicon_url' => $tenant->favicon_url,
            ]
        ]);
    }

    /**
     * Lista imóveis disponíveis do tenant (público)
     * GET /api/portal/imoveis
     */
    public function getImoveis(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');
        
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Buscar apenas imóveis ativos e disponíveis
        $imoveis = Property::where('tenant_id', $tenantId)
            ->where('status', 'disponivel')
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $imoveis,
            'total' => $imoveis->count()
        ]);
    }

    /**
     * Detalhes de um imóvel específico (público)
     * GET /api/portal/imoveis/{id}
     */
    public function getImovel(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');
        
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $imovel = Property::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('status', 'disponivel')
            ->where('is_active', true)
            ->first();

        if (!$imovel) {
            return response()->json(['error' => 'Property not found'], 404);
        }

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
