<?php

namespace App\Http\Controllers\Admin;

use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SystemLogsController
{
    /**
     * Listar logs do sistema com filtros
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Apenas admin e super_admin podem acessar
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $perPage = $request->input('per_page', 50);
        $search = $request->input('search');
        $category = $request->input('category');
        $level = $request->input('level');

        $query = SystemLog::query();

        // Super admin vê todos os tenants, admin vê apenas seu tenant
        if ($user->role !== 'super_admin') {
            $query->where('tenant_id', $user->tenant_id);
        }

        // Filtro de busca (mensagem ou ação)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        // Filtro de categoria
        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        // Filtro de nível
        if ($level && $level !== 'all') {
            $query->where('level', $level);
        }

        // Ordenar por mais recente
        $query->orderBy('created_at', 'desc');

        $logs = $query->paginate($perPage);

        return response()->json($logs);
    }

    /**
     * Limpar logs antigos (apenas super_admin)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'super_admin') {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $days = $request->input('days', 30);
        $deleted = SystemLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
            'message' => "Removidos {$deleted} logs com mais de {$days} dias"
        ]);
    }
}
