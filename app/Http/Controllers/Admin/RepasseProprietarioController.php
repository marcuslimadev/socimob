<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;

use App\Models\ContratoLocacao;
use App\Models\RepasseProprietario;
use App\Services\RepasseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RepasseProprietarioController extends Controller
{
    public function __construct(private readonly RepasseService $repasseService)
    {
    }

    public function index(Request $request)
    {
        $query = RepasseProprietario::query()
            ->with([
                'contrato:id,numero_contrato,locador_pessoa_id,locatario_pessoa_id,imovel_id',
                'contrato.locador:id,nome,email',
                'contrato.locatario:id,nome,email',
                'contrato.imovel:id,titulo,codigo',
            ])
            ->orderByDesc('competencia');

        if ($request->filled('contrato_id')) {
            $query->where('contrato_id', $request->input('contrato_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('competencia')) {
            $query->where('competencia', $request->input('competencia'));
        }

        return response()->json([
            'success' => true,
            'items' => $query->limit(300)->get(),
        ]);
    }

    public function show(int $id)
    {
        $item = RepasseProprietario::with([
            'contrato.locador:id,nome,email,cpf_cnpj,banco,agencia,conta,tipo_conta,pix_chave',
            'contrato.imovel:id,titulo,codigo',
            'cobranca',
        ])->find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Repasse não encontrado'], 404);
        }

        return response()->json(['success' => true, 'item' => $item]);
    }

    public function pagar(Request $request, int $id)
    {
        $item = RepasseProprietario::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Repasse não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'forma_pagamento' => 'nullable|string|max:50',
            'data_pagamento' => 'nullable|date',
            'observacao' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item->update([
            'status' => 'pago',
            'data_pagamento' => $validator->validated()['data_pagamento'] ?? now()->toDateString(),
            'forma_pagamento' => $validator->validated()['forma_pagamento'] ?? null,
        ]);

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }

    public function extrato(int $contratoId, Request $request)
    {
        $contrato = ContratoLocacao::find($contratoId);
        if (!$contrato) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $query = RepasseProprietario::where('contrato_id', $contratoId)
            ->orderByDesc('competencia');

        if ($request->filled('competencia_inicio')) {
            $query->where('competencia', '>=', $request->input('competencia_inicio'));
        }

        if ($request->filled('competencia_fim')) {
            $query->where('competencia', '<=', $request->input('competencia_fim'));
        }

        $items = $query->get();

        $totais = [
            'valor_aluguel_recebido' => $items->sum('valor_aluguel_recebido'),
            'valor_taxa_administracao' => $items->sum('valor_taxa_administracao'),
            'valor_deducoes' => $items->sum('valor_deducoes'),
            'valor_repasse' => $items->sum('valor_repasse'),
            'total_pago' => $items->where('status', 'pago')->sum('valor_repasse'),
            'total_pendente' => $items->where('status', 'pendente')->sum('valor_repasse'),
        ];

        return response()->json([
            'success' => true,
            'contrato' => $contrato->only(['id', 'numero_contrato', 'locador_pessoa_id']),
            'items' => $items,
            'totais' => $totais,
        ]);
    }
}
