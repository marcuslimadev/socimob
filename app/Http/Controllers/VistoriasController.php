<?php

namespace App\Http\Controllers;

use App\Models\Vistoria;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VistoriasController extends Controller
{
    /**
     * Listar vistorias
     * GET /api/vistorias
     */
    public function index(Request $request)
    {
        $query = $this->applyFilters(Vistoria::query(), $request);

        $perPage = (int) $request->query('per_page', 15);

        $vistorias = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($vistorias);
    }

    /**
     * Detalhes da vistoria
     * GET /api/vistorias/{id}
     */
    public function show(Request $request, $id)
    {
        $query = $this->applyTenantScope(Vistoria::query(), $request);

        $vistoria = $query->find($id);

        if (!$vistoria) {
            return response()->json(['error' => 'Vistoria not found'], 404);
        }

        return response()->json($vistoria);
    }

    /**
     * Exportar vistorias
     * GET /api/vistorias/export
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->applyFilters(Vistoria::query(), $request)
            ->orderBy('created_at', 'desc');

        $filename = 'vistorias_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id',
                'codigo',
                'status',
                'cliente_nome',
                'imovel_id',
                'tipo',
                'metragem',
                'mobiliado',
                'data_vistoria',
                'created_at',
            ]);

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $vistoria) {
                    fputcsv($handle, [
                        $vistoria->id,
                        $vistoria->codigo,
                        $vistoria->status,
                        $vistoria->cliente_nome,
                        $vistoria->imovel_id,
                        $vistoria->tipo,
                        $vistoria->metragem,
                        $vistoria->mobiliado ? 'sim' : 'nao',
                        optional($vistoria->data_vistoria)->format('Y-m-d H:i:s'),
                        optional($vistoria->created_at)->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function applyTenantScope($query, Request $request)
    {
        if ($request->attributes->has('tenant_id')) {
            $query->forTenant($request->attributes->get('tenant_id'));
        }

        return $query;
    }

    private function applyFilters($query, Request $request)
    {
        $query = $this->applyTenantScope($query, $request);

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

        return $query;
    }
}
