<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\User;
use App\Services\MercadoPagoService;
use App\Services\NFeIOService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommissionController extends Controller
{
    private $mercadoPago;
    private $nfeIO;

    public function __construct(MercadoPagoService $mercadoPago, NFeIOService $nfeIO)
    {
        $this->mercadoPago = $mercadoPago;
        $this->nfeIO = $nfeIO;
    }

    /**
     * Listar todas as comissões
     */
    public function index(Request $request)
    {
        try {
            $tenantId = $request->get('tenant_id');
            
            $query = Commission::with('corretor')
                ->where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc');

            // Filtro por status
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Filtro por corretor
            if ($request->has('corretor_id') && $request->corretor_id) {
                $query->where('corretor_id', $request->corretor_id);
            }

            $comissoes = $query->get()->map(function($c) {
                return [
                    'id' => $c->id,
                    'corretor_id' => $c->corretor_id,
                    'corretor_nome' => $c->corretor->name ?? 'N/A',
                    'valor_venda' => (float) $c->valor_venda,
                    'percentual' => (float) $c->percentual,
                    'valor_comissao' => (float) $c->valor_comissao,
                    'status' => $c->status,
                    'observacoes' => $c->observacoes,
                    'nfse_numero' => $c->nfse_numero,
                    'nfse_pdf_url' => $c->nfse_pdf_url,
                    'pago_em' => $c->pago_em?->toISOString(),
                    'created_at' => $c->created_at->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'comissoes' => $comissoes
            ]);

        } catch (\Exception $e) {
            Log::error('[CommissionController] Erro ao listar comissões', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar comissões'
            ], 500);
        }
    }

    /**
     * Obter detalhes de uma comissão
     */
    public function show(Request $request, $id)
    {
        try {
            $tenantId = $request->get('tenant_id');
            
            $comissao = Commission::with('corretor')
                ->where('tenant_id', $tenantId)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'id' => $comissao->id,
                'corretor_id' => $comissao->corretor_id,
                'corretor_nome' => $comissao->corretor->name ?? 'N/A',
                'valor_venda' => (float) $comissao->valor_venda,
                'percentual' => (float) $comissao->percentual,
                'valor_comissao' => (float) $comissao->valor_comissao,
                'status' => $comissao->status,
                'observacoes' => $comissao->observacoes,
                'nfse_numero' => $comissao->nfse_numero,
                'nfse_pdf_url' => $comissao->nfse_pdf_url,
                'comprovante_path' => $comissao->comprovante_path,
                'pago_em' => $comissao->pago_em?->toISOString(),
                'created_at' => $comissao->created_at->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('[CommissionController] Erro ao buscar comissão', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Comissão não encontrada'
            ], 404);
        }
    }

    /**
     * Criar nova comissão e gerar pagamento PIX
     */
    public function store(Request $request)
    {
        try {
            $tenantId = $request->get('tenant_id');

            // Validação
            $validator = Validator::make($request->all(), [
                'corretor_id' => 'required|exists:users,id',
                'valor_venda' => 'required|numeric|min:0.01',
                'percentual' => 'required|numeric|min:0.01|max:100',
                'observacoes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Dados inválidos',
                    'details' => $validator->errors()
                ], 422);
            }

            // Verificar se corretor pertence ao tenant
            $corretor = User::where('id', $request->corretor_id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$corretor) {
                return response()->json([
                    'success' => false,
                    'error' => 'Corretor não encontrado'
                ], 404);
            }

            // Criar comissão
            $comissao = new Commission();
            $comissao->tenant_id = $tenantId;
            $comissao->corretor_id = $request->corretor_id;
            $comissao->valor_venda = $request->valor_venda;
            $comissao->percentual = $request->percentual;
            $comissao->observacoes = $request->observacoes;
            $comissao->calcularComissao();
            $comissao->status = 'processando';
            $comissao->save();

            Log::info('[CommissionController] Comissão criada', [
                'commission_id' => $comissao->id,
                'corretor' => $corretor->name,
                'valor_comissao' => $comissao->valor_comissao
            ]);

            // Gerar pagamento PIX via Mercado Pago
            try {
                $pixData = $this->mercadoPago->criarPagamentoPix([
                    'transaction_amount' => (float) $comissao->valor_comissao,
                    'description' => "Comissão - {$corretor->name}",
                    'payer' => [
                        'email' => $corretor->email,
                        'first_name' => $corretor->name
                    ],
                    'external_reference' => "COMM-{$comissao->id}"
                ]);

                // Atualizar comissão com dados do PIX
                $comissao->mercadopago_payment_id = $pixData['id'];
                $comissao->mercadopago_qrcode = $pixData['qrcode'];
                $comissao->mercadopago_qrcode_base64 = $pixData['qrcode_base64'];
                $comissao->save();

                return response()->json([
                    'success' => true,
                    'comissao_id' => $comissao->id,
                    'payment_id' => $pixData['id'],
                    'qrcode' => $pixData['qrcode'],
                    'qrcode_base64' => $pixData['qrcode_base64'],
                    'valor_comissao' => (float) $comissao->valor_comissao
                ]);

            } catch (\Exception $e) {
                Log::error('[CommissionController] Erro ao gerar PIX', [
                    'commission_id' => $comissao->id,
                    'error' => $e->getMessage()
                ]);

                $comissao->status = 'pendente';
                $comissao->save();

                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao gerar pagamento PIX: ' . $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('[CommissionController] Erro ao criar comissão', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao processar comissão'
            ], 500);
        }
    }

    /**
     * Verificar status do pagamento
     */
    public function verificarStatus(Request $request, $id)
    {
        try {
            $tenantId = $request->get('tenant_id');
            
            $comissao = Commission::where('tenant_id', $tenantId)
                ->findOrFail($id);

            if (!$comissao->mercadopago_payment_id) {
                return response()->json([
                    'success' => true,
                    'status' => $comissao->status
                ]);
            }

            // Consultar status no Mercado Pago
            $pagamento = $this->mercadoPago->consultarPagamento($comissao->mercadopago_payment_id);

            Log::info('[CommissionController] Status do pagamento', [
                'commission_id' => $id,
                'mp_status' => $pagamento['status']
            ]);

            // Atualizar status se foi pago
            if ($pagamento['status'] === 'approved' && $comissao->status !== 'pago') {
                $comissao->marcarComoPago();

                // Emitir NFSe automaticamente
                $this->emitirNFSe($comissao);
            }

            return response()->json([
                'success' => true,
                'status' => $pagamento['status'],
                'status_detail' => $pagamento['status_detail'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('[CommissionController] Erro ao verificar status', [
                'commission_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao verificar status'
            ], 500);
        }
    }

    /**
     * Listar corretores
     */
    public function listarCorretores(Request $request)
    {
        try {
            $tenantId = $request->get('tenant_id');
            
            $corretores = User::where('tenant_id', $tenantId)
                ->whereIn('role', ['admin', 'user'])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'pix_key', 'pix_type', 'banco', 'agencia', 'conta']);

            return response()->json([
                'success' => true,
                'corretores' => $corretores
            ]);

        } catch (\Exception $e) {
            Log::error('[CommissionController] Erro ao listar corretores', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar corretores'
            ], 500);
        }
    }

    /**
     * Emitir NFSe via NFE.io
     */
    private function emitirNFSe(Commission $comissao)
    {
        try {
            if ($comissao->hasNFSe()) {
                Log::info('[CommissionController] NFSe já emitida', [
                    'commission_id' => $comissao->id,
                    'nfse_numero' => $comissao->nfse_numero
                ]);
                return;
            }

            $corretor = $comissao->corretor;
            
            $nfse = $this->nfeIO->emitirNFSe([
                'valorServicos' => (float) $comissao->valor_comissao,
                'descricao' => "Comissão sobre venda de imóvel - Valor da venda: R$ " . number_format($comissao->valor_venda, 2, ',', '.'),
                'tomador' => [
                    'nome' => $corretor->name,
                    'email' => $corretor->email,
                    'cpfCnpj' => $corretor->cpf ?? '',
                ],
                'observacoes' => $comissao->observacoes
            ]);

            $comissao->nfe_io_id = $nfse['id'];
            $comissao->nfse_numero = $nfse['numero'];
            $comissao->nfse_pdf_url = $nfse['pdfUrl'];
            $comissao->nfse_emitida_em = now();
            $comissao->save();

            Log::info('[CommissionController] NFSe emitida com sucesso', [
                'commission_id' => $comissao->id,
                'nfse_numero' => $nfse['numero']
            ]);

        } catch (\Exception $e) {
            Log::error('[CommissionController] Erro ao emitir NFSe', [
                'commission_id' => $comissao->id,
                'error' => $e->getMessage()
            ]);
            // Não lançar exceção - NFSe pode ser emitida manualmente depois
        }
    }
}
