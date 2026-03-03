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

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = CommissionInvoice::with(['corretor:id,name,email'])
            ->where('tenant_id', $user->tenant_id)
            ->orderByDesc('created_at');

        if ($request->filled('tipo_nota')) {
            $tipoNota = $request->input('tipo_nota');
            $query->where('financeiro_metadata->tipo_nota', $tipoNota);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('financeiro_status')) {
            $query->where('financeiro_status', $request->input('financeiro_status'));
        }

        $items = $query->limit(100)->get()->map(function (CommissionInvoice $invoice) {
            $metadata = $invoice->financeiro_metadata ?? [];

            return [
                'id' => $invoice->id,
                'tipo_nota' => $metadata['tipo_nota'] ?? 'corretagem',
                'corretor' => [
                    'id' => $invoice->corretor_id,
                    'name' => $invoice->corretor->name ?? 'N/A',
                    'email' => $invoice->corretor->email ?? null,
                ],
                'valor_total' => (float) $invoice->valor_total,
                'aliquota_iss' => (float) $invoice->aliquota_iss,
                'valor_iss' => (float) $invoice->valor_iss,
                'descricao_servico' => $invoice->descricao_servico,
                'status' => $invoice->status,
                'financeiro_status' => $invoice->financeiro_status,
                'forma_pagamento' => $metadata['forma_pagamento'] ?? null,
                'vencimento' => $invoice->financeiro_vencimento?->toDateString(),
                'nfse' => [
                    'numero' => $invoice->nfse_numero,
                    'pdf_url' => $invoice->nfse_pdf_url,
                    'xml_url' => $invoice->nfse_xml_url,
                    'integracao_id' => $invoice->integracao_id,
                ],
                'created_at' => $invoice->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
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
            'tipo_nota' => 'nullable|in:corretagem,aluguel',
            'descricao' => 'nullable|string',
            'competencia' => 'nullable|date',
            'tomador.nome' => 'required|string',
            'tomador.documento' => 'required|string',
            'tomador.email' => 'nullable|email',
            'tomador.telefone' => 'nullable|string',
            'tomador.endereco' => 'nullable|array',
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
        $tipoNota = $request->input('tipo_nota', 'corretagem');
        $aliquota = (float)$request->input('aliquota_iss', 0);
        $iss = $valor * ($aliquota / 100);
        $competencia = $request->input('competencia')
            ? Carbon::parse($request->input('competencia'))->toDateString()
            : Carbon::now()->toDateString();
        $financeiroDados = $request->input('financeiro', []);
        $descricao = $request->input('descricao')
            ?: $this->gerarDescricaoNota($tipoNota, $valor, $property);

        $invoice = CommissionInvoice::create([
            'tenant_id' => $user->tenant_id,
            'corretor_id' => $corretor->id,
            'lead_id' => $lead?->id,
            'property_id' => $property?->id,
            'valor_total' => $valor,
            'aliquota_iss' => $aliquota,
            'valor_iss' => $iss,
            'descricao_servico' => $descricao,
            'competencia' => $competencia,
            'status' => 'pending',
            'tomador_dados' => $request->input('tomador'),
            'financeiro_metadata' => array_merge($financeiroDados, [
                'tipo_nota' => $tipoNota,
            ]),
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
            'tipo_nota' => $tipoNota,
            'invoice' => $invoice->fresh(),
            'financial_transaction' => $transaction,
        ], 201);
    }

    private function gerarDescricaoNota(string $tipoNota, float $valor, ?Property $property): string
    {
        $prefixo = $tipoNota === 'aluguel'
            ? 'Intermediação de locação imobiliária'
            : 'Intermediação de corretagem imobiliária';

        $referenciaImovel = $property?->codigo
            ?? $property?->codigo_imovel
            ?? $property?->referencia_imovel;

        $sufixoImovel = $referenciaImovel ? ' - Imóvel: ' . $referenciaImovel : '';

        return $prefixo . $sufixoImovel . ' - Valor: R$ ' . number_format($valor, 2, ',', '.');
    }
}
