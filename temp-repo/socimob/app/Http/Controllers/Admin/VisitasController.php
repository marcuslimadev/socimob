<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\VisitasTablesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisitasController extends Controller
{
    /**
     * GET /api/admin/visitas
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        VisitasTablesManager::ensureVisitasTableExists();

        $visitas = DB::table('visitas')
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('data_hora', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $visitas,
            'total' => $visitas->count(),
        ]);
    }

    /**
     * PATCH /api/admin/visitas/{id}
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        VisitasTablesManager::ensureVisitasTableExists();

        $this->validate($request, [
            'status' => 'nullable|in:pendente,confirmada,cancelada,concluida',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        $visita = DB::table('visitas')
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$visita) {
            return response()->json(['error' => 'Visita not found'], 404);
        }

        $payload = [];
        if ($request->has('status')) {
            $payload['status'] = $request->input('status');
        }
        if ($request->has('observacoes')) {
            $payload['observacoes'] = $request->input('observacoes');
        }
        if (empty($payload)) {
            return response()->json(['error' => 'No changes provided'], 400);
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        DB::table('visitas')
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->update($payload);

        return response()->json(['success' => true]);
    }
}
