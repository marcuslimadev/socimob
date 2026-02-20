<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CobrancaContrato;
use App\Models\ContratoLocacao;
use App\Services\ContratoCobrancaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CobrancasContratoController extends Controller
{
    public function __construct(private readonly ContratoCobrancaService $contratoCobrancaService)
    {
    }

    public function index(Request $request)
    {
        $query = CobrancaContrato::query()
            ->with([
                'contrato:id,imovel_id,locador_pessoa_id,locatario_pessoa_id,status',
                'contrato.locador:id,nome,email',
                'contrato.locatario:id,nome,email',
                'documentoFiscal:id,cobranca_id,tipo,status,numero,url_pdf,url_xml',
            ])
            ->orderByDesc('id');

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

    public function gerar(Request $request, int $contratoId)
    {
        $validator = Validator::make($request->all(), [
            'competencia' => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $contrato = ContratoLocacao::find($contratoId);
        if (!$contrato) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $item = $this->contratoCobrancaService->gerarOuObterCobranca(
            $contrato,
            $validator->validated()['competencia'],
        );

        return response()->json([
            'success' => true,
            'item' => $item,
        ], 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        $item = CobrancaContrato::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Cobrança não encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|max:30',
            'valor_pago' => 'nullable|numeric|min:0',
            'data_pagamento' => 'nullable|date',
            'forma_pagamento' => 'nullable|string|max:50',
            'multa' => 'nullable|numeric|min:0',
            'juros' => 'nullable|numeric|min:0',
            'desconto' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $dados = $validator->validated();
        $item->fill($dados);

        if (isset($dados['multa']) || isset($dados['juros']) || isset($dados['desconto'])) {
            $item->valor_total = (float) $item->valor_base + (float) $item->multa + (float) $item->juros - (float) $item->desconto;
        }

        $item->save();

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }
}
