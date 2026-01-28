<?php

namespace App\Http\Controllers;

use App\Models\VistoriaSolicitacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VistoriaSolicitacoesController extends Controller
{
    /**
     * Criar solicitacao de vistoria
     * POST /api/vistorias/solicitacoes
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cliente_nome' => 'required|string|max:255',
            'tipo' => 'required|string|max:100',
            'imovel_id' => 'nullable|integer',
            'observacoes' => 'nullable|string',
            'pessoas' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $solicitacao = VistoriaSolicitacao::create([
            'tenant_id' => $request->attributes->get('tenant_id'),
            'codigo' => $data['codigo'] ?? null,
            'status' => 'solicitada',
            'cliente_nome' => $data['cliente_nome'],
            'tipo' => $data['tipo'],
            'imovel_id' => $data['imovel_id'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'pessoas' => $data['pessoas'] ?? null,
            'historico' => [
                [
                    'evento' => 'solicitada',
                    'descricao' => 'Solicitacao criada',
                    'data' => now()->toDateTimeString(),
                ]
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $solicitacao,
        ], 201);
    }
}
