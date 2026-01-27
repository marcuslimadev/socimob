<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Conversa;
use Illuminate\Http\Request;

class TempLeadCleanupController extends Controller
{
    /**
     * Lista leads que contenham um telefone específico
     */
    public function listByPhone(Request $request)
    {
        $phone = $request->input('phone', '');

        if (empty($phone)) {
            return response()->json(['error' => 'Phone parameter required'], 400);
        }

        $leads = Lead::where('telefone', 'like', "%{$phone}%")
            ->orWhere('whatsapp', 'like', "%{$phone}%")
            ->get(['id', 'nome', 'telefone', 'whatsapp', 'email', 'created_at']);

        return response()->json([
            'success' => true,
            'count' => $leads->count(),
            'leads' => $leads
        ]);
    }

    /**
     * Deleta leads com telefone específico
     */
    public function deleteByPhone(Request $request)
    {
        $phone = $request->input('phone', '');

        if (empty($phone)) {
            return response()->json(['error' => 'Phone parameter required'], 400);
        }

        $leads = Lead::where('telefone', 'like', "%{$phone}%")
            ->orWhere('whatsapp', 'like', "%{$phone}%")
            ->get();

        $deleted = [];

        foreach ($leads as $lead) {
            // Deletar conversas relacionadas
            $conversas = Conversa::where('lead_id', $lead->id)->get();
            foreach ($conversas as $conversa) {
                $conversa->mensagens()->delete();
                $conversa->delete();
            }

            $deleted[] = [
                'id' => $lead->id,
                'nome' => $lead->nome,
                'telefone' => $lead->telefone
            ];

            $lead->delete();
        }

        return response()->json([
            'success' => true,
            'deleted_count' => count($deleted),
            'deleted' => $deleted
        ]);
    }
}
