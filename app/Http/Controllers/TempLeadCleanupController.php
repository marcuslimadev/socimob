<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Conversa;
use App\Services\LeadAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    /**
     * Iniciar atendimento manual para um lead (TEMPORÁRIO - SEM AUTH)
     */
    public function iniciarAtendimento(Request $request, $id)
    {
        try {
            $lead = Lead::findOrFail($id);

            Log::info('[TempController] Iniciando atendimento manual', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome,
                'telefone' => $lead->telefone
            ]);

            // Iniciar atendimento via service
            $leadAutomationService = app()->make(LeadAutomationService::class);
            $resultado = $leadAutomationService->iniciarAtendimento($lead, true);

            if ($resultado['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Atendimento iniciado com sucesso',
                    'data' => $resultado
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $resultado['error'] ?? 'Erro desconhecido',
                    'data' => $resultado
                ], 400);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Lead não encontrado'
            ], 404);

        } catch (\Exception $e) {
            Log::error('[TempController] Erro ao iniciar atendimento', [
                'lead_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao iniciar atendimento: ' . $e->getMessage()
            ], 500);
        }
    }
}
