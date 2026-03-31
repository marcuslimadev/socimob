<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContratoCompraVenda;
use App\Models\Pessoa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContratosCompraVendaController extends Controller
{
    private array $baseRules = [
        'numero_contrato' => 'nullable|string|max:80',
        'imovel_id' => 'nullable|integer|exists:imo_properties,id',
        'vendedor_pessoa_id' => 'required|integer|exists:pessoas,id',
        'comprador_pessoa_id' => 'required|integer|exists:pessoas,id',
        'co_vendedores_ids' => 'nullable|array',
        'co_vendedores_ids.*' => 'integer|exists:pessoas,id',
        'co_compradores_ids' => 'nullable|array',
        'co_compradores_ids.*' => 'integer|exists:pessoas,id',
        'status' => 'nullable|string|max:50',
        'data_contrato' => 'nullable|date',
        'data_escritura_prevista' => 'nullable|date',
        'data_entrega_chaves' => 'nullable|date',
        'prazo_documentacao_dias' => 'nullable|integer|min:1|max:365',
        'prazo_escritura_dias' => 'nullable|integer|min:1|max:365',
        'prazo_registro_dias' => 'nullable|integer|min:1|max:365',
        'valor_total' => 'nullable|numeric|min:0',
        'valor_sinal' => 'nullable|numeric|min:0',
        'valor_parcela_final' => 'nullable|numeric|min:0',
        'multa_percentual' => 'nullable|numeric|min:0|max:100',
        'multa_moratoria_percentual' => 'nullable|numeric|min:0|max:100',
        'juros_percentual_mes' => 'nullable|numeric|min:0|max:100',
        'corretagem_valor' => 'nullable|numeric|min:0',
        'corretagem_responsavel' => 'nullable|string|max:120',
        'intermediadora_nome' => 'nullable|string|max:255',
        'intermediadora_documento' => 'nullable|string|max:40',
        'intermediadora_fantasia' => 'nullable|string|max:255',
        'objeto_descricao' => 'nullable|string',
        'matricula_numero' => 'nullable|string|max:120',
        'cartorio_nome' => 'nullable|string|max:255',
        'inscricao_cadastral' => 'nullable|string|max:120',
        'parcelas_pagamento' => 'nullable|array',
        'parcelas_pagamento.*.descricao' => 'nullable|string|max:100',
        'parcelas_pagamento.*.valor' => 'nullable|numeric|min:0',
        'parcelas_pagamento.*.texto' => 'nullable|string|max:10000',
        'clausulas' => 'nullable|array',
        'clausulas.*' => 'string|max:10000',
        'observacoes' => 'nullable|string',
        'metadata' => 'nullable|array',
        'testemunha_um_nome' => 'nullable|string|max:255',
        'testemunha_um_documento' => 'nullable|string|max:40',
        'testemunha_um_email' => 'nullable|email|max:255',
        'testemunha_dois_nome' => 'nullable|string|max:255',
        'testemunha_dois_documento' => 'nullable|string|max:40',
        'testemunha_dois_email' => 'nullable|email|max:255',
    ];

    public function index(Request $request)
    {
        $query = ContratoCompraVenda::query()
            ->with([
                'imovel:id,titulo,codigo,logradouro,numero,bairro,cidade,estado',
                'vendedor:id,nome,email,telefone,whatsapp',
                'comprador:id,nome,email,telefone,whatsapp',
            ])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'success' => true,
            'items' => $query->limit(200)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->baseRules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $item = ContratoCompraVenda::create($data);

        if (empty($item->numero_contrato)) {
            $item->update(['numero_contrato' => sprintf('CV-%06d', $item->id)]);
        }

        $this->atualizarPapeisPessoas($item);

        return response()->json([
            'success' => true,
            'item' => $this->carregarContrato($item->id),
        ], 201);
    }

    public function show(int $id)
    {
        $item = $this->carregarContrato($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        return response()->json(['success' => true, 'item' => $item]);
    }

    public function update(Request $request, int $id)
    {
        $item = ContratoCompraVenda::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->baseRules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item->update($validator->validated());
        $this->atualizarPapeisPessoas($item);

        return response()->json([
            'success' => true,
            'item' => $this->carregarContrato($item->id),
        ]);
    }

    public function destroy(int $id)
    {
        $item = ContratoCompraVenda::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $item->delete();

        return response()->json(['success' => true]);
    }

    private function carregarContrato(int $id): ?ContratoCompraVenda
    {
        return ContratoCompraVenda::with([
            'imovel:id,titulo,codigo,logradouro,numero,complemento,bairro,cidade,estado,cep,area_total,area_privativa,garagem',
            'vendedor:id,nome,email,telefone,whatsapp,cpf,cnpj,rg,orgao_expedidor,nacionalidade,estado_civil,profissao,data_nascimento,endereco,numero,complemento,bairro,cidade,estado,cep,conjuge_nome,conjuge_cpf,conjuge_rg,conjuge_profissao,conjuge_nacionalidade,conjuge_orgao_expedidor',
            'comprador:id,nome,email,telefone,whatsapp,cpf,cnpj,rg,orgao_expedidor,nacionalidade,estado_civil,profissao,data_nascimento,endereco,numero,complemento,bairro,cidade,estado,cep,conjuge_nome,conjuge_cpf,conjuge_rg,conjuge_profissao,conjuge_nacionalidade,conjuge_orgao_expedidor',
            'documentos',
        ])->find($id);
    }

    private function atualizarPapeisPessoas(ContratoCompraVenda $contrato): void
    {
        $ids = collect([
            $contrato->vendedor_pessoa_id,
            $contrato->comprador_pessoa_id,
            ...($contrato->co_vendedores_ids ?? []),
            ...($contrato->co_compradores_ids ?? []),
        ])->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $pessoas = Pessoa::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($pessoas as $pessoa) {
            if ((int) $pessoa->id === (int) $contrato->comprador_pessoa_id || in_array((int) $pessoa->id, $contrato->co_compradores_ids ?? [], true)) {
                $pessoa->adicionarPapel('comprador');
            }

            if ((int) $pessoa->id === (int) $contrato->vendedor_pessoa_id || in_array((int) $pessoa->id, $contrato->co_vendedores_ids ?? [], true)) {
                $pessoa->adicionarPapel('vendedor');
            }
        }
    }
}
