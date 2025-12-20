<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\VisitasTablesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $this->validate($request, [
            'property_id' => 'nullable|integer',
            'property_titulo' => 'nullable|string|max:255',
            'nome' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'required|string|max:50',
            'data_hora' => 'required|date',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        $dataHora = $request->input('data_hora');
        $now = date('Y-m-d H:i:s');

        $id = DB::table('visitas')->insertGetId([
            'tenant_id' => $tenantId,
            'property_id' => $request->input('property_id'),
            'property_titulo' => $request->input('property_titulo'),
            'nome' => $request->input('nome'),
            'email' => $request->input('email'),
            'telefone' => $request->input('telefone'),
            'data_hora' => date('Y-m-d H:i:s', strtotime($dataHora)),
            'status' => 'pendente',
            'observacoes' => $request->input('observacoes'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visita agendada com sucesso!',
            'id' => $id,
        ]);
    }
}
