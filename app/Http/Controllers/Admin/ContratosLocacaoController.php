<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;

use App\Models\ContratoLocacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ContratosLocacaoController extends Controller
{
    private array $baseRules = [
        'imovel_id' => 'nullable|integer',
        'locador_pessoa_id' => 'nullable|integer',
        'locatario_pessoa_id' => 'nullable|integer',
        'status' => 'nullable|string|max:50',
        'inicio' => 'nullable|date',
        'fim' => 'nullable|date',
        'dia_vencimento' => 'nullable|integer|min:1|max:31',
        'valor_aluguel' => 'nullable|numeric|min:0',
        'valor_condominio' => 'nullable|numeric|min:0',
        'valor_iptu' => 'nullable|numeric|min:0',
        'valor_taxa' => 'nullable|numeric|min:0',
        'valor_seguro' => 'nullable|numeric|min:0',
        'tipo_garantia' => 'nullable|string|max:50',
        'valor_garantia' => 'nullable|numeric|min:0',
        'comissao_administracao_percentual' => 'nullable|numeric|min:0|max:100',
        'observacoes' => 'nullable|string',
        'clausulas' => 'nullable|array',
        'indice_reajuste' => 'nullable|string|max:50',
        'periodicidade_reajuste' => 'nullable|string|max:50',
        'proximo_reajuste' => 'nullable|date',
        'metadata' => 'nullable|array',
    ];

    public function index(Request $request)
    {
        $query = ContratoLocacao::query()
            ->with(['imovel:id,titulo,codigo,cidade,bairro', 'locador:id,nome,email,telefone,whatsapp', 'locatario:id,nome,email,telefone,whatsapp'])
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
        $rules = $this->baseRules;
        $rules['locador_pessoa_id'] = 'required|integer';
        $rules['locatario_pessoa_id'] = 'required|integer';

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item = ContratoLocacao::create($validator->validated());

        return response()->json(['success' => true, 'item' => $item->load(['imovel:id,titulo,codigo', 'locador:id,nome', 'locatario:id,nome'])], 201);
    }

    public function show(int $id)
    {
        $item = ContratoLocacao::with([
            'imovel:id,titulo,codigo,cidade,bairro',
            'locador:id,nome,email,telefone,whatsapp,cpf_cnpj',
            'locatario:id,nome,email,telefone,whatsapp,cpf_cnpj',
            'cobrancas.documentoFiscal',
            'fiadores.pessoa:id,nome,email,telefone,cpf_cnpj',
            'reajustes',
            'repasses',
            'documentos',
        ])->find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        return response()->json(['success' => true, 'item' => $item]);
    }

    public function update(Request $request, int $id)
    {
        $item = ContratoLocacao::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->baseRules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item->update($validator->validated());

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }

    public function destroy(int $id)
    {
        $item = ContratoLocacao::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $item->delete();

        return response()->json(['success' => true]);
    }

    public function encerrar(Request $request, int $id)
    {
        $item = ContratoLocacao::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'motivo_rescisao' => 'required|string|max:500',
            'rescindido_em' => 'nullable|date',
            'multa_rescisao_calculada' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item->update([
            'status' => 'encerrado',
            'rescindido_em' => $validator->validated()['rescindido_em'] ?? now()->toDateString(),
            'motivo_rescisao' => $validator->validated()['motivo_rescisao'],
            'multa_rescisao_calculada' => $validator->validated()['multa_rescisao_calculada'] ?? null,
        ]);

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }

    public function renovar(Request $request, int $id)
    {
        $item = ContratoLocacao::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'novo_fim' => 'required|date|after:today',
            'novo_valor_aluguel' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $update = [
            'fim' => $validator->validated()['novo_fim'],
            'renovado_ate' => $validator->validated()['novo_fim'],
            'status' => 'ativo',
        ];

        if (!empty($validator->validated()['novo_valor_aluguel'])) {
            $update['valor_aluguel'] = $validator->validated()['novo_valor_aluguel'];
        }

        $item->update($update);

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }
}

