<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversa;
use Illuminate\Http\Request;

class PortalChatController extends Controller
{
    /**
     * POST /api/admin/portal-chat/{id}/take
     */
    public function take(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $conversa = Conversa::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$conversa) {
            return response()->json(['error' => 'Conversa not found'], 404);
        }

        if ($conversa->corretor_id && $conversa->corretor_id !== $user->id) {
            return response()->json([
                'error' => 'Conversa ja foi assumida por outro corretor'
            ], 409);
        }

        $conversa->update([
            'corretor_id' => $user->id,
            'status' => 'aguardando_corretor',
            'ultima_atividade' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conversa assumida com sucesso',
            'conversa' => $conversa,
        ]);
    }

    /**
     * POST /api/admin/portal-chat/{id}/release
     */
    public function release(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $conversa = Conversa::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$conversa) {
            return response()->json(['error' => 'Conversa not found'], 404);
        }

        if ($conversa->corretor_id && $conversa->corretor_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'error' => 'Somente o corretor responsavel pode liberar a conversa'
            ], 403);
        }

        $conversa->update([
            'corretor_id' => null,
            'status' => 'ativa',
            'ultima_atividade' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conversa liberada para IA',
            'conversa' => $conversa,
        ]);
    }
}
