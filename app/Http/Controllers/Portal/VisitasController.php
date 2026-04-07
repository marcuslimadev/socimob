<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;


use App\Services\VisitSchedulingService;
use App\Services\VisitasTablesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VisitasController extends Controller
{
    /**
     * POST /api/portal/visitas
     */
    public function agendar(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        VisitasTablesManager::ensureVisitasTableExists();

        $validator = Validator::make($request->all(), [
            'property_id' => 'nullable|integer',
            'lead_id' => 'nullable|integer',
            'property_titulo' => 'nullable|string|max:255',
            'nome' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'required|string|max:50',
            'data_hora' => 'required|string|max:50',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $visit = app(VisitSchedulingService::class)->schedule([
            'tenant_id' => $tenantId,
            'property_id' => $request->input('property_id'),
            'lead_id' => $request->input('lead_id'),
            'property_titulo' => $request->input('property_titulo'),
            'nome' => $request->input('nome'),
            'email' => $request->input('email'),
            'telefone' => $request->input('telefone'),
            'data_hora' => $request->input('data_hora'),
            'observacoes' => $request->input('observacoes'),
            'origem' => $request->input('origem') ?: 'portal_publico',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visita agendada com sucesso!',
            'id' => $visit['id'],
            'assigned_user_id' => $visit['assigned_user_id'],
        ]);
    }
}
