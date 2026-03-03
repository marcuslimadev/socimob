<?php
namespace App\Http\Controllers;

use App\Models\VistoriaContestacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VistoriaContestacoesController extends Controller
{
    /**
     * Listar contestacoes
     * GET /api/vistorias/contestacoes
     */
    public function index(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? (app()->bound('tenant') ? app('tenant')->id : null);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant não identificado'], 403);
        }

        $query = VistoriaContestacao::where('tenant_id', $tenantId);

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('vistoria_id')) {
            $query->where('vistoria_id', $request->vistoria_id);
        }

        // Filtro por período
        if ($request->filled('data_inicio')) {
            $query->whereDate('data_contestacao', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('data_contestacao', '<=', $request->data_fim);
        }

        $perPage = (int) $request->query('per_page', 15);

        $contestacoes = $query->orderBy('created_at', 'desc')
            ->with(['vistoria', 'responsavel'])
            ->paginate($perPage);

        return response()->json($contestacoes);
    }

    /**
     * Criar contestacao
     * POST /api/vistorias/contestacoes
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vistoria_id' => 'nullable|integer',
            'tipo' => 'required|string|in:dano,divergencia,outro',
            'descricao' => 'required|string',
            'cliente_nome' => 'nullable|string|max:255',
            'imovel_referencia' => 'nullable|string|max:255',
            'fotos' => 'nullable|array',
            'documentos' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $contestacao = VistoriaContestacao::create([
            'tenant_id' => $request->attributes->get('tenant_id'),
            'vistoria_id' => $data['vistoria_id'] ?? null,
            'codigo' => 'CONT-' . time(),
            'status' => 'apontada',
            'tipo' => $data['tipo'],
            'descricao' => $data['descricao'],
            'cliente_nome' => $data['cliente_nome'] ?? null,
            'imovel_referencia' => $data['imovel_referencia'] ?? null,
            'fotos' => $data['fotos'] ?? null,
            'documentos' => $data['documentos'] ?? null,
            'data_contestacao' => now(),
            'historico' => [
                [
                    'evento' => 'contestacao_criada',
                    'descricao' => 'Contestação criada',
                    'data' => now()->toDateTimeString(),
                ]
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $contestacao,
        ], 201);
    }

    /**
     * Visualizar contestacao
     * GET /api/vistorias/contestacoes/{id}
     */
    public function show(Request $request, $id)
    {
        $contestacao = VistoriaContestacao::with(['vistoria', 'responsavel'])->find($id);

        if (!$contestacao) {
            return response()->json([
                'error' => 'Contestacao not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $contestacao,
        ]);
    }

    /**
     * Atualizar status de contestacao
     * PUT /api/vistorias/contestacoes/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:apontada,em_analise,finalizada',
            'resolucao' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $contestacao = VistoriaContestacao::find($id);

        if (!$contestacao) {
            return response()->json([
                'error' => 'Contestacao not found',
            ], 404);
        }

        $oldStatus = $contestacao->status;
        $newStatus = $request->input('status');

        $contestacao->status = $newStatus;

        if ($request->filled('resolucao')) {
            $contestacao->resolucao = $request->input('resolucao');
        }

        if ($newStatus === 'finalizada' && !$contestacao->data_resolucao) {
            $contestacao->data_resolucao = now();
        }

        // Adicionar ao historico
        $historico = $contestacao->historico ?? [];
        $historico[] = [
            'evento' => 'status_alterado',
            'descricao' => "Status alterado de '{$oldStatus}' para '{$newStatus}'",
            'data' => now()->toDateTimeString(),
        ];
        $contestacao->historico = $historico;

        $contestacao->save();

        return response()->json([
            'success' => true,
            'data' => $contestacao,
        ]);
    }

    /**
     * Excluir contestacao
     * DELETE /api/vistorias/contestacoes/{id}
     */
    public function destroy($id)
    {
        $contestacao = VistoriaContestacao::find($id);

        if (!$contestacao) {
            return response()->json([
                'error' => 'Contestacao not found',
            ], 404);
        }

        $contestacao->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contestacao deletada com sucesso',
        ]);
    }
}
