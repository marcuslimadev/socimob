<?php

namespace App\Http\Controllers;

use App\Models\Pessoa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PessoasController extends Controller
{
    /**
     * Listar pessoas
     * GET /api/pessoas
     */
    public function index(Request $request)
    {
        $query = Pessoa::query();

        if ($request->attributes->has('tenant_id')) {
            $query->forTenant($request->attributes->get('tenant_id'));
        }

        // Filtros
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('ativo')) {
            $query->where('ativo', $request->ativo === 'true' || $request->ativo === '1');
        }

        // Busca global
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nome', 'ILIKE', "%{$search}%")
                  ->orWhere('cpf', 'ILIKE', "%{$search}%")
                  ->orWhere('cnpj', 'ILIKE', "%{$search}%")
                  ->orWhere('email', 'ILIKE', "%{$search}%")
                  ->orWhere('telefone', 'ILIKE', "%{$search}%")
                  ->orWhere('celular', 'ILIKE', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 15);

        $pessoas = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($pessoas);
    }

    /**
     * Criar pessoa
     * POST /api/pessoas
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'pais' => 'nullable|string|max:100',
            'telefone' => 'nullable|string|max:50',
            'celular' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'tipo' => 'required|string|in:fisica,juridica',
            'cpf' => 'nullable|string|max:14',
            'rg' => 'nullable|string|max:20',
            'orgao_expedidor' => 'nullable|string|max:50',
            'data_expedicao' => 'nullable|date',
            'cnh' => 'nullable|string|max:20',
            'data_nascimento' => 'nullable|date',
            'cnpj' => 'nullable|string|max:18',
            'razao_social' => 'nullable|string|max:255',
            'inscricao_estadual' => 'nullable|string|max:50',
            'inscricao_municipal' => 'nullable|string|max:50',
            'cep' => 'nullable|string|max:10',
            'estado' => 'nullable|string|max:2',
            'cidade' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'contatos' => 'nullable|array',
            'observacoes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['tenant_id'] = $request->attributes->get('tenant_id');
        $data['pais'] = $data['pais'] ?? 'Brasil';

        $pessoa = Pessoa::create($data);

        return response()->json([
            'success' => true,
            'data' => $pessoa,
        ], 201);
    }

    /**
     * Visualizar pessoa
     * GET /api/pessoas/{id}
     */
    public function show(Request $request, $id)
    {
        $pessoa = Pessoa::find($id);

        if (!$pessoa) {
            return response()->json([
                'error' => 'Pessoa not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $pessoa,
        ]);
    }

    /**
     * Atualizar pessoa
     * PUT /api/pessoas/{id}
     */
    public function update(Request $request, $id)
    {
        $pessoa = Pessoa::find($id);

        if (!$pessoa) {
            return response()->json([
                'error' => 'Pessoa not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome' => 'nullable|string|max:255',
            'pais' => 'nullable|string|max:100',
            'telefone' => 'nullable|string|max:50',
            'celular' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'tipo' => 'nullable|string|in:fisica,juridica',
            'cpf' => 'nullable|string|max:14',
            'rg' => 'nullable|string|max:20',
            'orgao_expedidor' => 'nullable|string|max:50',
            'data_expedicao' => 'nullable|date',
            'cnh' => 'nullable|string|max:20',
            'data_nascimento' => 'nullable|date',
            'cnpj' => 'nullable|string|max:18',
            'razao_social' => 'nullable|string|max:255',
            'inscricao_estadual' => 'nullable|string|max:50',
            'inscricao_municipal' => 'nullable|string|max:50',
            'cep' => 'nullable|string|max:10',
            'estado' => 'nullable|string|max:2',
            'cidade' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'contatos' => 'nullable|array',
            'observacoes' => 'nullable|string',
            'ativo' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $pessoa->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $pessoa,
        ]);
    }

    /**
     * Excluir pessoa
     * DELETE /api/pessoas/{id}
     */
    public function destroy($id)
    {
        $pessoa = Pessoa::find($id);

        if (!$pessoa) {
            return response()->json([
                'error' => 'Pessoa not found',
            ], 404);
        }

        $pessoa->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pessoa deletada com sucesso',
        ]);
    }
}
