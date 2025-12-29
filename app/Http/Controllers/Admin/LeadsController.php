<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\LeadAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LeadsController extends Controller
{
    private $leadAutomationService;

    public function __construct(LeadAutomationService $leadAutomationService)
    {
        $this->leadAutomationService = $leadAutomationService;
    }

    /**
     * Iniciar atendimento IA manual para um lead
     * 
     * POST /api/admin/leads/{id}/iniciar-atendimento
     */
    public function iniciarAtendimento(Request $request, $id)
    {
        try {
            $tenantId = $request->get('tenant_id');

            $lead = Lead::where('tenant_id', $tenantId)
                ->findOrFail($id);

            Log::info('[LeadsController] Iniciando atendimento IA manual', [
                'lead_id' => $lead->id,
                'admin_user' => $request->user()->name ?? 'N/A'
            ]);

            // Forçar início mesmo se já tiver conversa
            $forceStart = $request->input('force', false);

            $resultado = $this->leadAutomationService->iniciarAtendimento($lead, $forceStart);

            if ($resultado['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Atendimento IA iniciado com sucesso',
                    'data' => $resultado
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $resultado['error'],
                    'lead_id' => $lead->id
                ], 400);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Lead não encontrado'
            ], 404);

        } catch (\Exception $e) {
            Log::error('[LeadsController] Erro ao iniciar atendimento', [
                'lead_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao iniciar atendimento'
            ], 500);
        }
    }

    /**
     * Iniciar atendimento IA em lote
     * 
     * POST /api/admin/leads/iniciar-atendimento-lote
     */
    public function iniciarAtendimentoLote(Request $request)
    {
        try {
            $tenantId = $request->get('tenant_id');

            $validator = Validator::make($request->all(), [
                'lead_ids' => 'required|array|min:1',
                'lead_ids.*' => 'integer|exists:leads,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Dados inválidos',
                    'details' => $validator->errors()
                ], 422);
            }

            Log::info('[LeadsController] Iniciando atendimento IA em lote', [
                'total_leads' => count($request->lead_ids),
                'admin_user' => $request->user()->name ?? 'N/A'
            ]);

            $resultados = $this->leadAutomationService->iniciarAtendimentoEmLote($request->lead_ids);

            return response()->json([
                'success' => true,
                'message' => "Processados {$resultados['total']} leads",
                'data' => $resultados
            ]);

        } catch (\Exception $e) {
            Log::error('[LeadsController] Erro ao iniciar atendimento em lote', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao processar lote'
            ], 500);
        }
    }
}
