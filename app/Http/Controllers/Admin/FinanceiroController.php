<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionInvoice;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Services\FinancialIntegrationService;
use App\Services\NfseCommissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FinanceiroController extends Controller
{
    public function __construct(
        private readonly NfseCommissionService $nfseCommissionService,
        private readonly FinancialIntegrationService $financialIntegrationService
    ) {
    }

    public function emitirNfseComissao(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'corretor_id' => 'required|integer',
            'lead_id' => 'nullable|integer',
            'property_id' => 'nullable|integer',
            'valor' => 'required|numeric|min:0.01',
            'aliquota_iss' => 'nullable|numeric|min:0',
            'descricao' => 'required|string',
            'competencia' => 'nullable|date',
            'tomador.nome' => 'required|string',
            'tomador.documento' => 'required|string',
            'tomador.email' => 'nullable|email',
            'tomador.telefone' => 'nullable|string',
            'tomador.endereco' => 'nullable|string',
            'financeiro.vencimento' => 'nullable|date',
            'financeiro.forma_pagamento' => 'nullable|string',
            'financeiro.descricao' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos para emissão da NFSe',
                'errors' => $validator->errors(),
            ], 422);
        }

        $corretor = User::where('tenant_id', $user->tenant_id)->find($request->input('corretor_id'));
        if (!$corretor) {
            return response()->json(['message' => 'Corretor não encontrado'], 404);
        }

        $lead = $request->filled('lead_id')
            ? Lead::where('tenant_id', $user->tenant_id)->find($request->input('lead_id'))
            : null;

        $property = $request->filled('property_id')
            ? Property::where('tenant_id', $user->tenant_id)->find($request->input('property_id'))
            : null;

        $valor = (float)$request->input('valor');
        $aliquota = (float)$request->input('aliquota_iss', 0);
        $iss = $valor * ($aliquota / 100);
        $competencia = $request->input('competencia')
            ? Carbon::parse($request->input('competencia'))->toDateString()
            : now()->toDateString();
        $financeiroDados = $request->input('financeiro', []);

        $invoice = CommissionInvoice::create([
            'tenant_id' => $user->tenant_id,
            'corretor_id' => $corretor->id,
            'lead_id' => $lead?->id,
            'property_id' => $property?->id,
            'valor_total' => $valor,
            'aliquota_iss' => $aliquota,
            'valor_iss' => $iss,
            'descricao_servico' => $request->input('descricao'),
            'competencia' => $competencia,
            'status' => 'pending',
            'tomador_dados' => $request->input('tomador'),
            'financeiro_metadata' => $financeiroDados,
            'financeiro_vencimento' => $financeiroDados['vencimento'] ?? null,
        ]);

        try {
            $nfseData = $this->nfseCommissionService->emitir($invoice, $request->input('tomador'), $financeiroDados);
            $invoice->markAsIssued($nfseData);
            $financeiroStatus = $nfseData['financeiro_status'] ?? $invoice->financeiro_status;
        } catch (\Throwable $e) {
            $invoice->markAsFailed($e->getMessage());

            Log::error('Erro ao emitir NFSe de comissão', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao emitir NFSe de comissão',
                'error' => $e->getMessage(),
            ], 502);
        }

        $transaction = $this->financialIntegrationService->registrarRecebimentoComissao($invoice, $financeiroDados);
        $novoStatusFinanceiro = $financeiroStatus ?? 'lancado';

        if ($transaction) {
            $invoice->syncFinanceStatus($novoStatusFinanceiro === 'pendente' ? 'lancado' : $novoStatusFinanceiro);
        }

        return response()->json([
            'success' => true,
            'invoice' => $invoice->fresh(),
            'financial_transaction' => $transaction,
        ], 201);
    }
}
