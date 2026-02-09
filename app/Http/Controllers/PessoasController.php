<?php

namespace App\Http\Controllers;

use App\Models\Pessoa;
use App\Models\PessoaInteracao;
use App\Models\PessoaDocumento;
use App\Models\PessoaRelacionamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

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
                $q->where('nome', 'LIKE', "%{$search}%")
                  ->orWhere('cpf', 'LIKE', "%{$search}%")
                  ->orWhere('cnpj', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('telefone', 'LIKE', "%{$search}%")
                  ->orWhere('celular', 'LIKE', "%{$search}%");
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
        $pessoa = Pessoa::with([
            'corretorResponsavel:id,name,email',
            'indicadoPor:id,nome',
            'interacoes' => function($q) {
                $q->with('usuario:id,name')->limit(50);
            },
            'documentosAnexados',
            'relacionamentos.pessoaDestino:id,nome,tipo',
            'lead' // Incluir lead associado com todos os dados
        ])->find($id);

        if (!$pessoa) {
            return response()->json([
                'error' => 'Pessoa not found',
            ], 404);
        }

        // Adicionar estatísticas
        $pessoa->estatisticas = [
            'total_interacoes' => $pessoa->interacoes->count(),
            'total_documentos' => $pessoa->documentosAnexados->count(),
            'total_relacionamentos' => $pessoa->relacionamentos->count(),
            'total_indicacoes' => $pessoa->indicacoes()->count(),
        ];

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

    /**
     * Listar interações da pessoa
     * GET /api/pessoas/{id}/interacoes
     */
    public function getInteracoes(Request $request, $id)
    {
        $pessoa = Pessoa::find($id);
        if (!$pessoa) {
            return response()->json(['error' => 'Pessoa not found'], 404);
        }

        $interacoes = $pessoa->interacoes()
            ->with('usuario:id,name,email')
            ->orderBy('data_interacao', 'desc')
            ->paginate(20);

        return response()->json($interacoes);
    }

    /**
     * Adicionar interação
     * POST /api/pessoas/{id}/interacoes
     */
    public function addInteracao(Request $request, $id)
    {
        $pessoa = Pessoa::find($id);
        if (!$pessoa) {
            return response()->json(['error' => 'Pessoa not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'tipo' => 'required|string|in:atendimento,visita,ligacao,email,whatsapp,reuniao,proposta,contrato,outro',
            'assunto' => 'nullable|string|max:255',
            'descricao' => 'required|string',
            'resultado' => 'nullable|string|in:positivo,negativo,neutro,pendente',
            'proxima_acao' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['pessoa_id'] = $pessoa->id;
        $data['tenant_id'] = $pessoa->tenant_id;
        $data['user_id'] = $request->attributes->get('user_id');
        $data['data_interacao'] = now();

        $interacao = PessoaInteracao::create($data);

        // Atualizar contadores da pessoa
        $pessoa->increment('total_atendimentos');
        $pessoa->update([
            'ultimo_atendimento' => now(),
            'ultimo_contato' => now()
        ]);
        $pessoa->atualizarScore();

        return response()->json(['success' => true, 'data' => $interacao], 201);
    }

    /**
     * Listar documentos
     * GET /api/pessoas/{id}/documentos
     */
    public function getDocumentos($id)
    {
        $pessoa = Pessoa::find($id);
        if (!$pessoa) {
            return response()->json(['error' => 'Pessoa not found'], 404);
        }

        $documentos = $pessoa->documentosAnexados()
            ->with('uploadedBy:id,name', 'verificadoPor:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $documentos]);
    }

    /**
     * Upload de documento
     * POST /api/pessoas/{id}/documentos
     */
    public function uploadDocumento(Request $request, $id)
    {
        $pessoa = Pessoa::find($id);
        if (!$pessoa) {
            return response()->json(['error' => 'Pessoa not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'tipo' => 'required|string|in:rg,cpf,cnh,comprovante_renda,comprovante_residencia,contrato,procuracao,certidao,outro',
            'arquivo' => 'required|file|max:10240', // 10MB
            'nome' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string',
            'data_validade' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $file = $request->file('arquivo');
        $path = $file->store('documentos/pessoas/' . $pessoa->id, 'public');

        $documento = PessoaDocumento::create([
            'tenant_id' => $pessoa->tenant_id,
            'pessoa_id' => $pessoa->id,
            'tipo' => $request->tipo,
            'nome' => $request->nome ?? $file->getClientOriginalName(),
            'arquivo' => $path,
            'tamanho' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'observacoes' => $request->observacoes,
            'data_validade' => $request->data_validade,
            'uploaded_by' => $request->attributes->get('user_id'),
        ]);

        return response()->json(['success' => true, 'data' => $documento], 201);
    }

    /**
     * Deletar documento
     * DELETE /api/pessoas/documentos/{documentoId}
     */
    public function deleteDocumento($documentoId)
    {
        $documento = PessoaDocumento::find($documentoId);
        if (!$documento) {
            return response()->json(['error' => 'Documento not found'], 404);
        }

        // Deletar arquivo físico
        if (Storage::disk('public')->exists($documento->arquivo)) {
            Storage::disk('public')->delete($documento->arquivo);
        }

        $documento->delete();

        return response()->json(['success' => true, 'message' => 'Documento deletado']);
    }

    /**
     * Verificar documento
     * POST /api/pessoas/documentos/{documentoId}/verificar
     */
    public function verificarDocumento(Request $request, $documentoId)
    {
        $documento = PessoaDocumento::find($documentoId);
        if (!$documento) {
            return response()->json(['error' => 'Documento not found'], 404);
        }

        $documento->update([
            'verificado' => true,
            'verificado_em' => now(),
            'verificado_por' => $request->attributes->get('user_id'),
        ]);

        return response()->json(['success' => true, 'data' => $documento]);
    }

    /**
     * Listar relacionamentos
     * GET /api/pessoas/{id}/relacionamentos
     */
    public function getRelacionamentos($id)
    {
        $pessoa = Pessoa::find($id);
        if (!$pessoa) {
            return response()->json(['error' => 'Pessoa not found'], 404);
        }

        $relacionamentos = $pessoa->relacionamentos()
            ->with('pessoaDestino:id,nome,tipo,cpf,cnpj,telefone,celular')
            ->get();

        return response()->json(['success' => true, 'data' => $relacionamentos]);
    }

    /**
     * Adicionar relacionamento
     * POST /api/pessoas/{id}/relacionamentos
     */
    public function addRelacionamento(Request $request, $id)
    {
        $pessoa = Pessoa::find($id);
        if (!$pessoa) {
            return response()->json(['error' => 'Pessoa not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'pessoa_destino_id' => 'required|exists:pessoas,id',
            'tipo' => 'required|string|in:conjuge,socio,fiador,procurador,dependente,referencia,outro',
            'observacoes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $relacionamento = PessoaRelacionamento::create([
            'tenant_id' => $pessoa->tenant_id,
            'pessoa_origem_id' => $pessoa->id,
            'pessoa_destino_id' => $request->pessoa_destino_id,
            'tipo' => $request->tipo,
            'observacoes' => $request->observacoes,
        ]);

        $relacionamento->load('pessoaDestino:id,nome,tipo');

        return response()->json(['success' => true, 'data' => $relacionamento], 201);
    }

    /**
     * Deletar relacionamento
     * DELETE /api/pessoas/relacionamentos/{relacionamentoId}
     */
    public function deleteRelacionamento($relacionamentoId)
    {
        $relacionamento = PessoaRelacionamento::find($relacionamentoId);
        if (!$relacionamento) {
            return response()->json(['error' => 'Relacionamento not found'], 404);
        }

        $relacionamento->delete();

        return response()->json(['success' => true, 'message' => 'Relacionamento deletado']);
    }

    /**
     * Gerenciar papéis
     * POST /api/pessoas/{id}/papeis
     */
    public function gerenciarPapeis(Request $request, $id)
    {
        $pessoa = Pessoa::find($id);
        if (!$pessoa) {
            return response()->json(['error' => 'Pessoa not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'acao' => 'required|string|in:adicionar,remover',
            'papel' => 'required|string|in:cliente,proprietario,corretor,prestador_servico,inquilino,fiador,investidor',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        if ($request->acao === 'adicionar') {
            $pessoa->adicionarPapel($request->papel);
        } else {
            $pessoa->removerPapel($request->papel);
        }

        return response()->json(['success' => true, 'data' => $pessoa->fresh()]);
    }

    /**
     * Atualizar score
     * POST /api/pessoas/{id}/score
     */
    public function atualizarScore($id)
    {
        $pessoa = Pessoa::find($id);
        if (!$pessoa) {
            return response()->json(['error' => 'Pessoa not found'], 404);
        }

        $score = $pessoa->atualizarScore();

        return response()->json([
            'success' => true,
            'score' => $score,
            'data' => $pessoa
        ]);
    }
}
