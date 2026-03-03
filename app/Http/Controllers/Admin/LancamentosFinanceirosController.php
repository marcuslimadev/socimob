<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;


use App\Models\BaixaFinanceira;
use App\Models\LancamentoFinanceiro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LancamentosFinanceirosController extends Controller
{
    public function index(Request $request)
    {
        $query = LancamentoFinanceiro::query()
            ->with(['pessoa:id,nome,email,telefone,whatsapp', 'contrato:id,status', 'cobranca:id,competencia,status', 'baixas'])
            ->orderByDesc('id');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'success' => true,
            'items' => $query->limit(300)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contrato_id' => 'nullable|integer',
            'cobranca_id' => 'nullable|integer',
            'pessoa_id' => 'nullable|integer',
            'tipo' => 'required|in:conta_receber,conta_pagar,transferencia',
            'categoria' => 'nullable|string|max:80',
            'descricao' => 'nullable|string|max:255',
            'competencia' => 'nullable|date',
            'vencimento' => 'nullable|date',
            'valor' => 'required|numeric|min:0.01',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $dados = $validator->validated();
        $dados['valor_em_aberto'] = $dados['valor'];
        $dados['status'] = 'aberto';

        $item = LancamentoFinanceiro::create($dados);

        return response()->json(['success' => true, 'item' => $item], 201);
    }

    public function registrarBaixa(Request $request, int $id)
    {
        $item = LancamentoFinanceiro::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Lançamento não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'data_baixa' => 'required|date',
            'valor_baixa' => 'required|numeric|min:0.01',
            'meio_pagamento' => 'nullable|string|max:50',
            'referencia' => 'nullable|string|max:100',
            'status_conciliacao' => 'nullable|in:pendente,conciliado,divergente',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $dados = $validator->validated();
        $dados['lancamento_id'] = $item->id;

        $baixa = BaixaFinanceira::create($dados);

        $item->valor_em_aberto = max(0, (float) $item->valor_em_aberto - (float) $baixa->valor_baixa);
        $item->status = $item->valor_em_aberto <= 0 ? 'liquidado' : 'parcial';
        $item->save();

        return response()->json([
            'success' => true,
            'item' => $item->fresh('baixas'),
            'baixa' => $baixa,
        ], 201);
    }
}
