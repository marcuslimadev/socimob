<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Conversa;
use App\Models\Lead;
use App\Models\Mensagem;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * POST /api/portal/chat/start
     */
    public function start(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');
        $user = $request->user();

        if (!$tenantId || !$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $lead = Lead::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->first();

        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $conversa = Conversa::where('tenant_id', $tenantId)
            ->where('lead_id', $lead->id)
            ->where('canal', 'portal')
            ->where('status', '!=', 'encerrada')
            ->orderBy('id', 'desc')
            ->first();

        if (!$conversa) {
            $telefone = $lead->whatsapp ?: $lead->telefone ?: ('portal:' . $user->id);
            $conversa = Conversa::create([
                'tenant_id' => $tenantId,
                'lead_id' => $lead->id,
                'telefone' => $telefone,
                'status' => 'ativa',
                'canal' => 'portal',
                'iniciada_em' => now(),
                'ultima_atividade' => now(),
            ]);
        }

        return $this->buildConversaResponse($conversa);
    }

    /**
     * GET /api/portal/chat/{id}
     */
    public function show(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');
        $user = $request->user();

        if (!$tenantId || !$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $conversa = $this->resolveConversa($tenantId, $user, $id);
        if (!$conversa) {
            return response()->json(['error' => 'Chat not found'], 404);
        }

        return $this->buildConversaResponse($conversa);
    }

    /**
     * GET /api/portal/chat/{id}/mensagens
     */
    public function mensagens(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');
        $user = $request->user();

        if (!$tenantId || !$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $conversa = $this->resolveConversa($tenantId, $user, $id);
        if (!$conversa) {
            return response()->json(['error' => 'Chat not found'], 404);
        }

        $sinceId = (int) $request->query('since_id', 0);

        $mensagens = Mensagem::where('conversa_id', $conversa->id)
            ->when($sinceId > 0, fn ($q) => $q->where('id', '>', $sinceId))
            ->orderBy('sent_at', 'asc')
            ->get();

        $conversa->load('corretor');

        return response()->json([
            'success' => true,
            'data' => $mensagens,
            'conversa' => [
                'id' => $conversa->id,
                'corretor_id' => $conversa->corretor_id,
                'corretor_nome' => $conversa->corretor?->name,
                'status' => $conversa->status,
            ]
        ]);
    }

    /**
     * POST /api/portal/chat/{id}/mensagens
     */
    public function send(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');
        $user = $request->user();

        if (!$tenantId || !$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $this->validate($request, [
            'content' => 'required|string|max:2000',
        ]);

        $conversa = $this->resolveConversa($tenantId, $user, $id);
        if (!$conversa) {
            return response()->json(['error' => 'Chat not found'], 404);
        }

        $lead = Lead::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->first();

        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        if ($conversa->corretor_id) {
            Mensagem::create([
                'tenant_id' => $tenantId,
                'conversa_id' => $conversa->id,
                'direction' => 'incoming',
                'message_type' => 'text',
                'content' => $data['content'],
                'status' => 'received',
                'sent_at' => now(),
            ]);

            $conversa->update(['ultima_atividade' => now(), 'status' => 'aguardando_corretor']);

            return response()->json(['success' => true, 'message' => 'Mensagem recebida']);
        }

        $result = $this->whatsappService->processPortalMessage($conversa, $lead, $data['content']);

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    private function resolveConversa(int $tenantId, $user, $id): ?Conversa
    {
        $query = Conversa::where('tenant_id', $tenantId)
            ->where('id', $id);

        if ($user->role === 'client') {
            $leadId = Lead::where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->value('id');

            $query->where('lead_id', $leadId);
        }

        return $query->first();
    }

    private function buildConversaResponse(Conversa $conversa)
    {
        $conversa->load('corretor');

        $mensagens = Mensagem::where('conversa_id', $conversa->id)
            ->orderBy('sent_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'conversa' => $conversa,
            'corretor_nome' => $conversa->corretor?->name,
            'mensagens' => $mensagens,
        ]);
    }
}
