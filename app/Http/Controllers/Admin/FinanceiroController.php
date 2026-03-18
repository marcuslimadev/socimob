<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;


use App\Models\CommissionInvoice;
use App\Models\DocumentoFiscal;
use App\Models\Lead;
use App\Models\Pessoa;
use App\Models\Property;
use App\Models\User;
use App\Services\FinancialIntegrationService;
use App\Services\NfseDanfseService;
use App\Services\NfseCommissionService;
use App\Services\NfseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FinanceiroController extends Controller
{
    public function __construct(
        private readonly NfseCommissionService $nfseCommissionService,
        private readonly FinancialIntegrationService $financialIntegrationService,
        private readonly NfseService $nfseService,
        private readonly NfseDanfseService $nfseDanfseService
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

        $commissionItems = $query->limit(100)->get()->map(
            fn (CommissionInvoice $invoice) => $this->mapCommissionInvoice($invoice)
        );

        $documentosQuery = DocumentoFiscal::with(['tomador:id,nome,razao_social,tipo,cpf,cnpj,email'])
            ->where('tenant_id', $user->tenant_id)
            ->orderByDesc('created_at');

        if ($request->filled('tipo_nota')) {
            $documentosQuery->where('contexto_emissao', $request->input('tipo_nota'));
        }

        if ($request->filled('status')) {
            $documentosQuery->where('status', $request->input('status'));
        }

        $documentosItems = $documentosQuery->limit(100)->get()->map(
            fn (DocumentoFiscal $documento) => $this->mapDocumentoFiscal($documento)
        );

        $items = $commissionItems
            ->concat($documentosItems)
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    public function showNotaServico(Request $request, string $registroTipo, int $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($registroTipo === 'commission_invoice') {
            $invoice = CommissionInvoice::with(['corretor:id,name,email'])
                ->where('tenant_id', $user->tenant_id)
                ->find($id);

            if (!$invoice) {
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }

            return response()->json([
                'success' => true,
                'item' => $this->mapCommissionInvoice($invoice),
            ]);
        }

        if ($registroTipo === 'documento_fiscal') {
            $documento = DocumentoFiscal::with(['tomador:id,nome,razao_social,tipo,cpf,cnpj,email'])
                ->where('tenant_id', $user->tenant_id)
                ->find($id);

            if (!$documento) {
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }

            return response()->json([
                'success' => true,
                'item' => $this->mapDocumentoFiscal($documento),
            ]);
        }

        return response()->json(['message' => 'Tipo de nota fiscal inválido'], 404);
    }

    public function downloadDanfse(Request $request, string $registroTipo, int $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($registroTipo === 'commission_invoice') {
            $invoice = CommissionInvoice::with(['corretor:id,name,email', 'property'])
                ->where('tenant_id', $user->tenant_id)
                ->find($id);

            if (!$invoice) {
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }

            $pdf = $this->nfseDanfseService->gerarParaCommissionInvoice($invoice);
            $filename = 'danfse-comissao-' . ($invoice->nfse_numero ?: $invoice->id) . '.pdf';

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        }

        if ($registroTipo === 'documento_fiscal') {
            $documento = DocumentoFiscal::with(['tomador:id,nome,razao_social,tipo,cpf,cnpj,email,telefone,celular,cep,estado,cidade,bairro,endereco,numero,complemento,inscricao_municipal'])
                ->where('tenant_id', $user->tenant_id)
                ->find($id);

            if (!$documento) {
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }

            $pdf = $this->nfseDanfseService->gerarParaDocumentoFiscal($documento);
            $filename = 'danfse-manual-' . ($documento->numero ?: $documento->id) . '.pdf';

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        }

        return response()->json(['message' => 'Tipo de nota fiscal inválido'], 404);
    }

    public function sincronizarDocumentoFiscal(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $documento = DocumentoFiscal::with(['tomador:id,nome,razao_social,tipo,cpf,cnpj,email'])
            ->where('tenant_id', $user->tenant_id)
            ->find($id);

        if (!$documento) {
            return response()->json(['message' => 'Documento fiscal não encontrado'], 404);
        }

        $integracaoId = data_get($documento->payload, 'integracao_id');
        if (!$integracaoId) {
            return response()->json(['message' => 'Documento fiscal sem identificador de integração'], 422);
        }

        try {
            $numeroAnterior = $documento->numero;
            $pdfAnterior = $documento->url_pdf;
            $xmlAnterior = $documento->url_xml;
            $nfseData = $this->nfseService->consultar($user->tenant_id, (string) $integracaoId, [
                'documento_fiscal_id' => $documento->id,
                'contexto_emissao' => $documento->contexto_emissao,
            ]);

            $statusAnterior = $documento->status;
            $statusAtualizado = $this->normalizarStatusNfse($nfseData['status'] ?? null, $documento->status);
            $payloadAtualizado = array_merge($documento->payload ?? [], [
                'integracao_id' => $nfseData['integracao_id'] ?? $integracaoId,
                'nfe_status' => $nfseData['status'] ?? null,
                'ultima_sincronizacao_nfse' => now()->toIso8601String(),
            ]);

            $documento->update([
                'status' => $statusAtualizado,
                'numero' => $this->resolverNumeroNfse($nfseData['numero'] ?? null, $documento->numero),
                'codigo_verificacao' => $nfseData['codigo_verificacao'] ?? $documento->codigo_verificacao,
                'emitida_em' => $statusAtualizado === 'issued'
                    ? ($documento->emitida_em ?? now())
                    : $documento->emitida_em,
                'url_pdf' => $nfseData['pdf_url'] ?? $documento->url_pdf,
                'url_xml' => $nfseData['xml_url'] ?? $documento->url_xml,
                'retorno' => $nfseData['raw_response'] ?? $documento->retorno,
                'payload' => $payloadAtualizado,
            ]);

            $documentoAtualizado = $documento->fresh(['tomador:id,nome,razao_social,tipo,cpf,cnpj,email']);

            return response()->json([
                'success' => true,
                'updated' => $statusAnterior !== $statusAtualizado
                    || $numeroAnterior !== $documentoAtualizado->numero
                    || $pdfAnterior !== $documentoAtualizado->url_pdf
                    || $xmlAnterior !== $documentoAtualizado->url_xml,
                'item' => $this->mapDocumentoFiscal($documentoAtualizado),
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao sincronizar NFSe manual', [
                'documento_fiscal_id' => $documento->id,
                'integracao_id' => $integracaoId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $this->normalizarMensagemErroEmissao($e),
                'error' => $e->getMessage(),
            ], $this->determinarStatusErroEmissao($e));
        }
    }

    public function emitirNfseComissao(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $contextoEmissao = $request->input('contexto_emissao', 'comissao');

        if (in_array($contextoEmissao, ['locatario', 'construtora', 'proprietario'], true)) {
            return $this->emitirDocumentoFiscalManual($request, $user->tenant_id, $contextoEmissao);
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

        if (!$this->validarDocumentoFederal((string) $request->input('tomador.documento'))) {
            return response()->json([
                'message' => 'CPF/CNPJ do tomador é inválido',
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
                'tomador_documento' => $this->nfseService->somenteDigitos($request->input('tomador.documento')),
            ]);

            return response()->json([
                'message' => $this->normalizarMensagemErroEmissao($e),
                'error' => $e->getMessage(),
            ], $this->determinarStatusErroEmissao($e));
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

    private function emitirDocumentoFiscalManual(Request $request, int $tenantId, string $contextoEmissao)
    {
        $validator = Validator::make($request->all(), [
            'pessoa_tomador_id' => 'nullable|integer',
            'property_id' => 'nullable|integer',
            'valor' => 'required|numeric|min:0.01',
            'aliquota_iss' => 'nullable|numeric|min:0',
            'descricao' => 'nullable|string',
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

        if (!$this->validarDocumentoFederal((string) $request->input('tomador.documento'))) {
            return response()->json([
                'message' => 'CPF/CNPJ do tomador é inválido',
            ], 422);
        }

        $tomadorPessoa = $request->filled('pessoa_tomador_id')
            ? Pessoa::where('tenant_id', $tenantId)->find($request->input('pessoa_tomador_id'))
            : null;

        if ($request->filled('pessoa_tomador_id') && !$tomadorPessoa) {
            return response()->json(['message' => 'Tomador não encontrado'], 404);
        }

        $property = $request->filled('property_id')
            ? Property::where('tenant_id', $tenantId)->find($request->input('property_id'))
            : null;

        $valor = (float) $request->input('valor');
        $aliquota = (float) $request->input('aliquota_iss', 0);
        $valorImpostos = $valor * ($aliquota / 100);
        $descricao = $request->input('descricao')
            ?: $this->gerarDescricaoDocumentoFiscalManual(
                $contextoEmissao,
                $valor,
                $property,
                $tomadorPessoa,
                $request->input('tomador', [])
            );
        $financeiroDados = $request->input('financeiro', []);

        $documento = DocumentoFiscal::create([
            'tenant_id' => $tenantId,
            'locatario_pessoa_id' => $contextoEmissao === 'locatario' ? $tomadorPessoa?->id : null,
            'tomador_pessoa_id' => $tomadorPessoa?->id,
            'property_id' => $property?->id,
            'contexto_emissao' => $contextoEmissao,
            'tipo' => match ($contextoEmissao) {
                'locatario' => 'nfse_locatario',
                'proprietario' => 'nfse_proprietario',
                default => 'nfse_construtora',
            },
            'status' => 'pending',
            'valor_servico' => $valor,
            'valor_impostos' => $valorImpostos,
            'payload' => [
                'tomador' => $request->input('tomador'),
                'financeiro' => $financeiroDados,
                'descricao_servico' => $descricao,
                'aliquota_iss' => $aliquota,
            ],
        ]);

        try {
            $payload = $this->montarPayloadDocumentoFiscal($documento, $request->input('tomador'), $financeiroDados, $descricao);
            $nfseData = $this->nfseService->emitir($tenantId, $payload, [
                'documento_fiscal_id' => $documento->id,
                'contexto_emissao' => $contextoEmissao,
            ]);

            $documento->update([
                'status' => 'issued',
                'numero' => $nfseData['numero'] ?? null,
                'codigo_verificacao' => $nfseData['codigo_verificacao'] ?? null,
                'emitida_em' => now(),
                'url_pdf' => $nfseData['pdf_url'] ?? null,
                'url_xml' => $nfseData['xml_url'] ?? null,
                'retorno' => $nfseData['raw_response'] ?? null,
                'payload' => array_merge($documento->payload ?? [], [
                    'integracao_id' => $nfseData['integracao_id'] ?? null,
                    'nfe_payload' => $payload,
                ]),
            ]);
        } catch (\Throwable $e) {
            $documento->update([
                'status' => 'error',
                'retorno' => ['error' => $e->getMessage()],
            ]);

            Log::error('Erro ao emitir NFSe manual', [
                'documento_fiscal_id' => $documento->id,
                'contexto_emissao' => $contextoEmissao,
                'error' => $e->getMessage(),
                'tomador_documento' => $this->nfseService->somenteDigitos($request->input('tomador.documento')),
            ]);

            return response()->json([
                'message' => $this->normalizarMensagemErroEmissao($e),
                'error' => $e->getMessage(),
            ], $this->determinarStatusErroEmissao($e));
        }

        return response()->json([
            'success' => true,
            'tipo_nota' => $contextoEmissao,
            'documento_fiscal' => $documento->fresh('tomador'),
        ], 201);
    }

    private function montarPayloadDocumentoFiscal(DocumentoFiscal $documento, array $tomador, array $financeiro, string $descricao): array
    {
        $documentoTomador = $this->nfseService->somenteDigitos($tomador['documento'] ?? null);

        $payload = [
            'cityServiceCode' => $documento->city_service_code ?: env('NFE_IO_SERVICE_CODE', '01.01'),
            'description' => $descricao,
            'servicesAmount' => (float) $documento->valor_servico,
            'borrower' => [
                'federalTaxNumber' => $documentoTomador,
                'name' => $tomador['nome'] ?? null,
                'email' => $tomador['email'] ?? null,
            ],
            'additionalInformation' => $this->montarInformacoesDocumentoFiscal($documento, $financeiro),
        ];

        $enderecoTomador = $this->nfseService->normalizarEndereco($tomador['endereco'] ?? null);
        if (!empty($enderecoTomador)) {
            $payload['borrower']['address'] = $enderecoTomador;
        }

        return $this->nfseService->limparPayload($payload);
    }

    private function montarInformacoesDocumentoFiscal(DocumentoFiscal $documento, array $financeiro): string
    {
        $partes = [
            'Documento fiscal #' . $documento->id,
            'Contexto: ' . ($documento->contexto_emissao ?? 'manual'),
        ];

        if ($documento->property_id) {
            $partes[] = 'Imóvel #' . $documento->property_id;
        }

        if (!empty($financeiro['forma_pagamento'])) {
            $partes[] = 'Pagamento: ' . $financeiro['forma_pagamento'];
        }

        if (!empty($financeiro['vencimento'])) {
            $partes[] = 'Vencimento: ' . $financeiro['vencimento'];
        }

        return implode(' | ', $partes);
    }

    private function gerarDescricaoDocumentoFiscalManual(
        string $contextoEmissao,
        float $valor,
        ?Property $property,
        ?Pessoa $tomador,
        array $tomadorData = []
    ): string
    {
        $prefixo = match ($contextoEmissao) {
            'construtora' => 'Serviços imobiliários prestados para construtora',
            'proprietario' => 'Serviços de corretagem imobiliária pela intermediação de venda do imóvel',
            default => 'Cobrança de locação imobiliária para locatário',
        };

        $referenciaImovel = $property?->codigo
            ?? $property?->codigo_imovel
            ?? $property?->referencia_imovel;

        $partes = [$prefixo];

        if ($referenciaImovel) {
            $partes[] = 'Imóvel: ' . $referenciaImovel;
        }

        $nomeTomador = $tomador?->razao_social
            ?: $tomador?->nome
            ?: ($tomadorData['nome'] ?? 'Tomador avulso');

        $partes[] = 'Tomador: ' . $nomeTomador;
        $partes[] = 'Valor: R$ ' . number_format($valor, 2, ',', '.');

        return implode(' - ', $partes);
    }

    private function mapCommissionInvoice(CommissionInvoice $invoice): array
    {
        $metadata = $invoice->financeiro_metadata ?? [];

        return [
            'id' => $invoice->id,
            'registro_tipo' => 'commission_invoice',
            'contexto_emissao' => 'comissao',
            'tipo_nota' => $metadata['tipo_nota'] ?? 'corretagem',
            'titulo' => 'Corretagem',
            'corretor' => [
                'id' => $invoice->corretor_id,
                'name' => $invoice->corretor->name ?? 'N/A',
                'email' => $invoice->corretor->email ?? null,
            ],
            'tomador' => $invoice->tomador_dados,
            'valor_total' => (float) $invoice->valor_total,
            'aliquota_iss' => (float) $invoice->aliquota_iss,
            'valor_iss' => (float) $invoice->valor_iss,
            'descricao_servico' => $invoice->descricao_servico,
            'status' => $invoice->status,
            'financeiro_status' => $invoice->financeiro_status,
            'erro_detalhe' => $invoice->erro_integracao,
            'forma_pagamento' => $metadata['forma_pagamento'] ?? null,
            'vencimento' => $invoice->financeiro_vencimento?->toDateString(),
            'nfse' => [
                'numero' => $invoice->nfse_numero,
                'pdf_url' => $invoice->nfse_pdf_url,
                'xml_url' => $invoice->nfse_xml_url,
                'integracao_id' => $invoice->integracao_id,
                'codigo_verificacao' => $invoice->nfse_codigo_verificacao,
                'rps' => $invoice->nfse_rps,
                'emitida_em' => null,
                'status_externo' => data_get($invoice->retorno_integracao, 'status'),
            ],
            'created_at' => $invoice->created_at?->toIso8601String(),
        ];
    }

    private function mapDocumentoFiscal(DocumentoFiscal $documento): array
    {
        $payload = $documento->payload ?? [];
        $tomador = $documento->tomador;
        $tomadorData = $payload['tomador'] ?? [];

        return [
            'id' => $documento->id,
            'registro_tipo' => 'documento_fiscal',
            'contexto_emissao' => $documento->contexto_emissao ?? 'manual',
            'tipo_nota' => $documento->contexto_emissao ?? $documento->tipo,
            'titulo' => match ($documento->contexto_emissao) {
                'construtora' => 'Construtora',
                'proprietario' => 'Proprietário',
                default => 'Locatário',
            },
            'corretor' => null,
            'tomador' => [
                'id' => $tomador?->id,
                'nome' => $tomador?->razao_social ?: $tomador?->nome ?: ($tomadorData['nome'] ?? 'Tomador'),
                'documento' => $tomador?->cnpj ?: $tomador?->cpf ?: ($tomadorData['documento'] ?? null),
                'email' => $tomador?->email ?: ($tomadorData['email'] ?? null),
            ],
            'valor_total' => (float) $documento->valor_servico,
            'aliquota_iss' => (float) data_get($payload, 'aliquota_iss', 0),
            'valor_iss' => (float) $documento->valor_impostos,
            'descricao_servico' => data_get($payload, 'descricao_servico', 'Documento fiscal emitido'),
            'status' => $documento->status,
            'financeiro_status' => data_get($payload, 'financeiro.forma_pagamento') ? 'emitido_manual' : 'n/a',
            'erro_detalhe' => data_get($documento->retorno, 'message')
                ?: data_get($documento->retorno, 'error.message')
                ?: data_get($documento->retorno, 'errors.0.message'),
            'forma_pagamento' => data_get($payload, 'financeiro.forma_pagamento'),
            'vencimento' => data_get($payload, 'financeiro.vencimento'),
            'nfse' => [
                'numero' => $documento->numero,
                'pdf_url' => $documento->url_pdf,
                'xml_url' => $documento->url_xml,
                'integracao_id' => data_get($payload, 'integracao_id'),
                'codigo_verificacao' => $documento->codigo_verificacao,
                'rps' => data_get($documento->retorno, 'rps') ?: data_get($documento->payload, 'rps'),
                'emitida_em' => $documento->emitida_em?->toIso8601String(),
                'status_externo' => data_get($payload, 'nfe_status'),
            ],
            'created_at' => $documento->created_at?->toIso8601String(),
        ];
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

    private function resolverNumeroNfse(mixed $numeroRecebido, mixed $numeroAtual): ?string
    {
        $numeroNormalizado = is_scalar($numeroRecebido) ? trim((string) $numeroRecebido) : '';

        if ($numeroNormalizado !== '' && $numeroNormalizado !== '0') {
            return $numeroNormalizado;
        }

        $numeroExistente = is_scalar($numeroAtual) ? trim((string) $numeroAtual) : '';

        return $numeroExistente !== '' ? $numeroExistente : null;
    }

    private function normalizarStatusNfse(?string $statusExterno, string $statusAtual): string
    {
        return match (strtolower(trim((string) $statusExterno))) {
            'issued', 'authorized' => 'issued',
            'processing', 'pending', 'created', 'draft' => 'pending',
            'cancelled', 'canceled' => 'cancelled',
            'denied', 'rejected', 'error', 'failed' => 'error',
            default => $statusAtual,
        };
    }

    private function normalizarMensagemErroEmissao(\Throwable $e): string
    {
        $message = trim($e->getMessage());

        if ($message === '') {
            return 'Erro ao emitir NFSe';
        }

        if (str_contains($message, 'borrower.federalTaxNumber')) {
            return 'CPF/CNPJ do tomador foi rejeitado pela NFe.io';
        }

        return $message;
    }

    private function determinarStatusErroEmissao(\Throwable $e): int
    {
        $message = $e->getMessage();

        if (str_contains($message, 'borrower.federalTaxNumber')) {
            return 422;
        }

        if (str_contains($message, 'Credenciais da NFe.io não configuradas')) {
            return 500;
        }

        return 502;
    }

    private function validarDocumentoFederal(string $documento): bool
    {
        $digits = preg_replace('/\D+/', '', $documento);

        if (!$digits) {
            return false;
        }

        return match (strlen($digits)) {
            11 => $this->validarCpf($digits),
            14 => $this->validarCnpj($digits),
            default => false,
        };
    }

    private function validarCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($digitIndex = 9; $digitIndex < 11; $digitIndex++) {
            $sum = 0;

            for ($index = 0; $index < $digitIndex; $index++) {
                $sum += ((int) $cpf[$index]) * (($digitIndex + 1) - $index);
            }

            $digit = ((10 * $sum) % 11) % 10;

            if ((int) $cpf[$digitIndex] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private function validarCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $calculateDigit = function (string $base, array $factors): int {
            $sum = 0;

            foreach ($factors as $index => $factor) {
                $sum += ((int) $base[$index]) * $factor;
            }

            $remainder = $sum % 11;

            return $remainder < 2 ? 0 : 11 - $remainder;
        };

        $base = substr($cnpj, 0, 12);
        $firstDigit = $calculateDigit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $secondDigit = $calculateDigit($base . $firstDigit, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $cnpj === $base . $firstDigit . $secondDigit;
    }
}
