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

        $perPage = (int) $request->query('per_page', 15);

        $vistorias = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($vistorias);
    }
}
