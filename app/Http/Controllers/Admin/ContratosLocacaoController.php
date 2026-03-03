<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;


use App\Models\ContratoLocacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContratosLocacaoController extends Controller
{
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
        $validator = Validator::make($request->all(), [
            'imovel_id' => 'nullable|integer',
            'locador_pessoa_id' => 'required|integer',
            'locatario_pessoa_id' => 'required|integer',
            'status' => 'nullable|string|max:50',
            'inicio' => 'nullable|date',
            'fim' => 'nullable|date',
            'dia_vencimento' => 'nullable|integer|min:1|max:31',
            'valor_aluguel' => 'nullable|numeric|min:0',
            'valor_condominio' => 'nullable|numeric|min:0',
            'valor_iptu' => 'nullable|numeric|min:0',
            'valor_taxa' => 'nullable|numeric|min:0',
            'valor_seguro' => 'nullable|numeric|min:0',
            'indice_reajuste' => 'nullable|string|max:50',
            'periodicidade_reajuste' => 'nullable|string|max:50',
            'proximo_reajuste' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item = ContratoLocacao::create($validator->validated());

        return response()->json(['success' => true, 'item' => $item], 201);
    }

    public function show(int $id)
    {
        $item = ContratoLocacao::with([
            'imovel:id,titulo,codigo,cidade,bairro',
            'locador:id,nome,email,telefone,whatsapp',
            'locatario:id,nome,email,telefone,whatsapp',
            'cobrancas.documentoFiscal',
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

        $validator = Validator::make($request->all(), [
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
            'indice_reajuste' => 'nullable|string|max:50',
            'periodicidade_reajuste' => 'nullable|string|max:50',
            'proximo_reajuste' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item->update($validator->validated());

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }
}
