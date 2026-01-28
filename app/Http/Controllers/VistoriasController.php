<?php

namespace App\\Http\\Controllers;

use App\\Models\\Vistoria;
use Illuminate\\Http\\Request;

class VistoriasController extends Controller
{
    /**
     * Listar vistorias
     * GET /api/vistorias
     */
    public function index(Request $request)
    {
        $query = Vistoria::query();

        if ($request->attributes->has('tenant_id')) {
            $query->forTenant($request->attributes->get('tenant_id'));
        }

        if ($request->filled('codigo')) {
            $query->where('codigo', 'like', '%' . $request->codigo . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('cliente')) {
            $query->where('cliente_nome', 'like', '%' . $request->cliente . '%');
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('imovel_id')) {
            $query->where('imovel_id', $request->imovel_id);
        }

        if ($request->filled('mobiliado')) {
            $query->where('mobiliado', filter_var($request->mobiliado, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('metragem_min')) {
            $query->where('metragem', '>=', $request->metragem_min);
        }

        if ($request->filled('metragem_max')) {
            $query->where('metragem', '<=', $request->metragem_max);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('data_vistoria', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('data_vistoria', '<=', $request->data_fim);
        }

        if ($request->filled('vistoriador')) {
            $query->whereJsonContains('vistoriadores', $request->vistoriador);
        }

        if ($request->filled('pessoa')) {
            $query->whereJsonContains('pessoas', $request->pessoa);
        }

        $perPage = (int) $request->query('per_page', 15);

        $vistorias = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($vistorias);
    }
}
