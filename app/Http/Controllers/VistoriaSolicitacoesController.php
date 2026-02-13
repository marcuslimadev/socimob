<?php

namespace App\Http\Controllers;

use App\Models\VistoriaSolicitacao;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VistoriaSolicitacoesController extends Controller
{
    private $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Listar solicitacoes de vistoria
     * GET /api/vistorias/solicitacoes
     */
    public function index(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? (app()->bound('tenant') ? app('tenant')->id : null);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant não identificado'], 403);
        }

        $query = VistoriaSolicitacao::where('tenant_id', $tenantId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->query('per_page', 15);

        $solicitacoes = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($solicitacoes);
    }

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

        // Enviar notificação por e-mail
        $destinatarios = $this->getDestinatariosVistoria();
        $this->emailService->enviarNovaSolicitacaoVistoria($solicitacao, $destinatarios);

        return response()->json([
            'success' => true,
            'data' => $solicitacao,
        ], 201);
    }

    /**
     * Atualizar status de solicitacao
     * PUT /api/vistorias/solicitacoes/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:solicitada,designada,andamento,concluida,cancelada',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $tenantId = $request->attributes->get('tenant_id')
            ?? (app()->bound('tenant') ? app('tenant')->id : null);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant não identificado'], 403);
        }

        $solicitacao = VistoriaSolicitacao::where('tenant_id', $tenantId)->find($id);

        if (!$solicitacao) {
            return response()->json([
                'error' => 'Solicitacao not found',
            ], 404);
        }

        // Atualizar status
        $oldStatus = $solicitacao->status;
        $newStatus = $request->input('status');

        $solicitacao->status = $newStatus;

        // Adicionar ao historico
        $historico = $solicitacao->historico ?? [];
        $historico[] = [
            'evento' => 'status_alterado',
            'descricao' => "Status alterado de '{$oldStatus}' para '{$newStatus}'",
            'data' => now()->toDateTimeString(),
        ];
        $solicitacao->historico = $historico;

        $solicitacao->save();

        // Enviar notificação por e-mail
        $destinatarios = $this->getDestinatariosVistoria();
        $this->emailService->enviarStatusVistoriaAlterado($solicitacao, $oldStatus, $newStatus, $destinatarios);

        return response()->json([
            'success' => true,
            'data' => $solicitacao,
        ]);
    }

    /**
     * Obter lista de destinatários para notificações de vistoria
     */
    private function getDestinatariosVistoria()
    {
        // Buscar emails de administradores/vistoriadores
        $users = app('db')->table('users')
            ->where('is_active', true)
            ->whereIn('role', ['admin', 'vistoriador'])
            ->pluck('email')
            ->toArray();

        return array_filter($users);
    }
}
