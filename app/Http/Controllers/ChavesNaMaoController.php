<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\ChavesNaMaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChavesNaMaoController extends Controller
{
    private ChavesNaMaoService $chavesNaMaoService;

    public function __construct(ChavesNaMaoService $chavesNaMaoService)
    {
        $this->chavesNaMaoService = $chavesNaMaoService;
    }

    /**
     * Testa integração enviando um lead específico
     */
    public function test(Request $request)
    {
        $leadId = $request->input('lead_id');

        if (!$leadId) {
            // Buscar primeiro lead disponível
            $lead = Lead::whereNull('chaves_na_mao_sent_at')
                ->whereNotNull('email')
                ->first();
        } else {
            $lead = Lead::find($leadId);
        }

        if (!$lead) {
            return response()->json([
                'success' => false,
                'error' => 'Lead não encontrado'
            ], 404);
        }

        $result = $this->chavesNaMaoService->sendLead($lead);

        return response()->json([
            'success' => $result['success'],
            'lead_id' => $lead->id,
            'lead_nome' => $lead->nome,
            'result' => $result
        ]);
    }

    /**
     * Retry de leads falhados
     */
    public function retry()
    {
        $results = $this->chavesNaMaoService->retryFailedLeads();

        return response()->json([
            'success' => true,
            'results' => $results
        ]);
    }

    /**
     * Status da integração
     */
    public function status()
    {
        $stats = [
            'pending' => Lead::where('chaves_na_mao_status', 'pending')->count(),
            'sent' => Lead::where('chaves_na_mao_status', 'sent')->count(),
            'error' => Lead::where('chaves_na_mao_status', 'error')->count(),
            'not_sent' => Lead::whereNull('chaves_na_mao_status')->count(),
        ];

        $lastErrors = Lead::where('chaves_na_mao_status', 'error')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get(['id', 'nome', 'chaves_na_mao_error', 'chaves_na_mao_retries', 'updated_at']);

        $lastSent = Lead::where('chaves_na_mao_status', 'sent')
            ->orderBy('chaves_na_mao_sent_at', 'desc')
            ->limit(10)
            ->get(['id', 'nome', 'chaves_na_mao_sent_at']);

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'last_errors' => $lastErrors,
            'last_sent' => $lastSent
        ]);
    }

    /**
     * Força reenvio de um lead específico
     */
    public function resend(Request $request)
    {
        $leadId = $request->input('lead_id');

        if (!$leadId) {
            return response()->json([
                'success' => false,
                'error' => 'lead_id obrigatório'
            ], 400);
        }

        $lead = Lead::find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'error' => 'Lead não encontrado'
            ], 404);
        }

        // Resetar status para permitir reenvio
        $lead->update([
            'chaves_na_mao_status' => null,
            'chaves_na_mao_sent_at' => null,
            'chaves_na_mao_response' => null,
            'chaves_na_mao_error' => null,
            'chaves_na_mao_retries' => 0
        ]);

        $result = $this->chavesNaMaoService->sendLead($lead);

        return response()->json([
            'success' => $result['success'],
            'lead_id' => $lead->id,
            'lead_nome' => $lead->nome,
            'result' => $result
        ]);
    }
}
