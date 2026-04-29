<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Conversa;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConversationAssignmentNotificationService;
use App\Services\EvolutionApiService;
use App\Services\MetaCloudGateway;
use App\Services\TwilioService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class ConversasController extends Controller
{
    private function isAdminRole(?string $role): bool
    {
        return in_array($role, ['admin', 'super_admin'], true);
    }

    private function isBrokerRole(?string $role): bool
    {
        return in_array($role, ['corretor', 'agent'], true);
    }

    private function brokerCanAccessConversation($user, $conversa): bool
    {
        if (!$this->isBrokerRole($user->role ?? null)) {
            return true;
        }

        return !empty($conversa->corretor_id) && (int) $conversa->corretor_id === (int) $user->id;
    }

    /**
     * Listar todas as conversas do corretor/admin
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $tenantId = $request->attributes->get('tenant_id');
            
            $query = DB::table('conversas')
                ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                ->leftJoin('users as corretor', 'conversas.corretor_id', '=', 'corretor.id')
                ->select(
                    'conversas.*',
                    'leads.nome as lead_nome',
                    'leads.telefone as lead_telefone',
                    'leads.email as lead_email',
                    'leads.observacoes as lead_observacoes',
                    'leads.classificacao as lead_classificacao',
                    'leads.status as lead_status',
                    'corretor.name as corretor_nome'
                )
                ->where(function ($q) use ($tenantId) {
                    // Compat: alguns registros antigos podem ter conversas.tenant_id nulo
                    $q->where('conversas.tenant_id', $tenantId)
                      ->orWhere('leads.tenant_id', $tenantId);
                });
            
            // Corretor só enxerga conversas atribuídas a ele. A fila livre fica com o admin para distribuição.
            if ($this->isBrokerRole($user->role ?? null)) {
                $conversas = $query
                    ->where('conversas.corretor_id', $user->id)
                    ->orderByRaw('COALESCE(conversas.ultima_atividade, conversas.created_at) DESC')
                    ->orderBy('conversas.created_at', 'desc')
                    ->get();
            } else {
                // Admin vê todas as conversas do tenant.
                $conversas = $query
                    ->orderByRaw('COALESCE(conversas.ultima_atividade, conversas.created_at) DESC')
                    ->orderBy('conversas.created_at', 'desc')
                    ->get();
            }
            
            // Verificar se coluna message_type existe (uma vez, fora do loop)
            $hasMsgTypeColumn = false;
            try {
                $hasMsgTypeColumn = Schema::hasColumn('mensagens', 'message_type');
            } catch (\Exception $e) {
                Log::warning('Erro ao verificar coluna message_type', ['error' => $e->getMessage()]);
            }

            // ── Batch load: evita N+1 no loop abaixo (3 queries totais) ──────
            $allIds = collect($conversas)->pluck('id')->filter()->all();

            // 1) Última mensagem por conversa
            $ultimasMensagensMap = [];
            if (!empty($allIds)) {
                $maxIds = DB::table('mensagens')
                    ->whereIn('conversa_id', $allIds)
                    ->select('conversa_id', DB::raw('MAX(id) as max_id'))
                    ->groupBy('conversa_id')
                    ->pluck('max_id', 'conversa_id')
                    ->toArray();

                if (!empty($maxIds)) {
                    $ultimasMensagensMap = DB::table('mensagens')
                        ->whereIn('id', array_values($maxIds))
                        ->get()
                        ->keyBy('conversa_id')
                        ->toArray();
                }
            }

            // 2) Contagem de não lidas por conversa
            $naoLidasMap = [];
            if (!empty($allIds)) {
                $naoLidasMap = DB::table('mensagens')
                    ->whereIn('conversa_id', $allIds)
                    ->where('direction', 'incoming')
                    ->whereNull('read_at')
                    ->select('conversa_id', DB::raw('COUNT(*) as total'))
                    ->groupBy('conversa_id')
                    ->get()
                    ->pluck('total', 'conversa_id')
                    ->toArray();
            }

            // 3) Conversas com intervenção humana necessária (outgoing 2h+ sem reply)
            $needsInterventionSet = [];
            if (!empty($allIds)) {
                try {
                    $interventionQuery = DB::table('mensagens as m')
                        ->whereIn('m.conversa_id', $allIds)
                        ->where('m.direction', 'outgoing')
                        ->whereNotNull('m.sent_at')
                        ->where('m.sent_at', '<', Carbon::now()->subHours(2))
                        ->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))->from('mensagens as newer')
                                ->whereColumn('newer.conversa_id', 'm.conversa_id')
                                ->where('newer.direction', 'outgoing')
                                ->whereColumn('newer.sent_at', '>', 'm.sent_at');
                        })
                        ->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))->from('mensagens as reply')
                                ->whereColumn('reply.conversa_id', 'm.conversa_id')
                                ->where('reply.direction', 'incoming')
                                ->whereColumn('reply.created_at', '>', 'm.sent_at');
                        });

                    if ($hasMsgTypeColumn) {
                        $interventionQuery->where('m.message_type', 'sms');
                    }

                    $needsInterventionSet = array_flip(
                        $interventionQuery->distinct()->pluck('m.conversa_id')->toArray()
                    );
                } catch (\Exception $e) {
                    Log::warning('Erro ao calcular needs_human_intervention batch', ['error' => $e->getMessage()]);
                }
            }
            // ─────────────────────────────────────────────────────────────────

            // Adicionar informações extras
            foreach ($conversas as &$conversa) {
                $conversa->lead_nome = $this->sanitizeUtf8($conversa->lead_nome ?? null);
                $conversa->lead_email = $this->sanitizeUtf8($conversa->lead_email ?? null);
                $conversa->corretor_nome = $this->sanitizeUtf8($conversa->corretor_nome ?? null);

                // Última mensagem (batch)
                $ultimaMensagem = $ultimasMensagensMap[$conversa->id] ?? null;
                $conversa->ultima_mensagem = $ultimaMensagem
                    ? $this->sanitizeUtf8(substr((string) $ultimaMensagem->content, 0, 100))
                    : null;

                // Não lidas (batch)
                $conversa->mensagens_nao_lidas = (int) ($naoLidasMap[$conversa->id] ?? 0);

                // Indicar se está em fila ou atribuída
                $conversa->em_fila = is_null($conversa->corretor_id);
                $conversa->atribuida_a_mim = $conversa->corretor_id == $user->id;

                // Intervenção humana (batch)
                $conversa->needs_human_intervention = isset($needsInterventionSet[$conversa->id]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $conversas
            ]);
            
        } catch (\Exception $e) {
            Log::error('[ConversasController] Erro ao carregar conversas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar conversas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function sanitizeUtf8($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = (string) $value;
        if ($text === '') {
            return $text;
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        return $clean === false ? preg_replace('/[\x00-\x1F\x7F]/u', '', $text) : $clean;
    }
    
    /**
     * Pegar próxima conversa da fila (sistema de fila de táxi)
     */
    public function pegarProxima(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'As conversas são distribuídas por um administrador.'
        ], 403);
    }
    
    /**
     * Devolver conversa para a fila
     */
    public function devolverParaFila(Request $request, $id)
    {
        try {
            $user = $request->user();
            $tenantId = $request->attributes->get('tenant_id');
            
            $conversa = DB::table('conversas')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$conversa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
            }
            
            // Verificar permissão
            if ($this->isBrokerRole($user->role ?? null) && $conversa->corretor_id != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para devolver esta conversa'
                ], 403);
            }
            
            // Devolver para fila
            DB::table('conversas')
                ->where('id', $id)
                ->update([
                    'corretor_id' => null,
                    'status' => 'aguardando_corretor',
                    'updated_at' => Carbon::now()
                ]);

            if (!empty($conversa->lead_id)) {
                DB::table('leads')
                    ->where('id', $conversa->lead_id)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'corretor_id' => null,
                        'status' => 'qualificado',
                        'updated_at' => Carbon::now(),
                    ]);
            }

            $returnedConversation = Conversa::with('lead')->find($id);
            if ($returnedConversation) {
                app(ConversationAssignmentNotificationService::class)
                    ->notifyAwaitingDistribution($returnedConversation, $returnedConversation->lead);
            }
            
            \App\Models\SystemLog::info(
                \App\Models\SystemLog::CATEGORY_AUTOMATION,
                'conversa_devolvida',
                'Conversa devolvida para a fila',
                [
                    'conversa_id' => $id,
                    'corretor_id' => $user->id,
                    'tenant_id' => $tenantId
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Conversa devolvida para a fila'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao devolver conversa',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Estatísticas da fila
     */
    public function estatisticasFila(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');
            $user = $request->user();

            $scopeConversas = DB::table('conversas')
                ->where('tenant_id', $tenantId)
                ->where('status', 'ativa');

            if ($this->isBrokerRole($user->role ?? null)) {
                $scopeConversas->where('corretor_id', $user->id);
            }

            $scopedConversationIds = (clone $scopeConversas)->pluck('id');

            $mensagensNaoLidasQuery = DB::table('mensagens')
                ->where('direction', 'incoming')
                ->whereNull('read_at');

            if ($scopedConversationIds->isEmpty()) {
                $mensagensNaoLidas = 0;
                $conversasComNaoLidas = 0;
            } else {
                $mensagensNaoLidas = (clone $mensagensNaoLidasQuery)
                    ->whereIn('conversa_id', $scopedConversationIds)
                    ->count();

                $conversasComNaoLidas = (clone $mensagensNaoLidasQuery)
                    ->whereIn('conversa_id', $scopedConversationIds)
                    ->distinct('conversa_id')
                    ->count('conversa_id');
            }
            
            $statsQuery = fn () => DB::table('conversas')
                ->where('conversas.tenant_id', $tenantId)
                ->where('conversas.status', 'ativa')
                ->when($this->isBrokerRole($user->role ?? null), fn ($query) => $query->where('conversas.corretor_id', $user->id));

            $stats = [
                'em_fila' => $this->isBrokerRole($user->role ?? null)
                    ? 0
                    : $statsQuery()->whereNull('conversas.corretor_id')->count(),

                'atribuidas' => $statsQuery()->whereNotNull('conversas.corretor_id')->count(),

                'total_ativas' => $statsQuery()->count(),

                'por_corretor' => $statsQuery()
                    ->join('users', 'conversas.corretor_id', '=', 'users.id')
                    ->whereNotNull('conversas.corretor_id')
                    ->select('users.name', DB::raw('COUNT(*) as total'))
                    ->groupBy('users.id', 'users.name')
                    ->get(),

                // Total real de mensagens incoming não lidas no escopo do usuário.
                'mensagens_nao_lidas' => $mensagensNaoLidas,

                // Quantidade de conversas com pelo menos uma não lida.
                'conversas_com_nao_lidas' => $conversasComNaoLidas,
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Listar mensagens de uma conversa
     */
    public function mensagens(Request $request, $id)
    {
        try {
            $user = $request->user();
            $tenantId = $request->attributes->get('tenant_id');
            
            // Verificar se conversa existe e pertence ao tenant
            $conversa = DB::table('conversas')
                ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                ->leftJoin('users as corretor', 'conversas.corretor_id', '=', 'corretor.id')
                ->select(
                    'conversas.id',
                    'conversas.tenant_id',
                    'conversas.corretor_id',
                    'leads.nome as lead_nome',
                    'corretor.name as corretor_nome'
                )
                ->where('conversas.id', $id)
                ->where('conversas.tenant_id', $tenantId)
                ->first();
            
            if (!$conversa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
            }

            // Corretor só pode ver mensagens de conversas atribuídas a ele.
            if (!$this->brokerCanAccessConversation($user, $conversa)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar esta conversa'
                ], 403);
            }
            
            // Buscar mensagens
            $mensagens = DB::table('mensagens')
                ->where('conversa_id', $id)
                ->orderBy('created_at', 'asc')
                ->get();

            $hasUserIdColumn = Schema::hasColumn('mensagens', 'user_id');

            $senderNamesByUserId = [];
            if ($hasUserIdColumn) {
                $userIds = $mensagens
                    ->filter(fn ($m) => ($m->direction ?? null) === 'outgoing' && !empty($m->user_id))
                    ->pluck('user_id')
                    ->unique()
                    ->values();

                if ($userIds->count() > 0) {
                    $senderNamesByUserId = DB::table('users')
                        ->whereIn('id', $userIds->all())
                        ->pluck('name', 'id')
                        ->toArray();
                }
            }

            $leadName = $conversa->lead_nome ?: 'Cliente';
            $fallbackOutgoingName = $conversa->corretor_nome ?: 'Atendente';
            $assistantName = $this->resolveAssistantDisplayName((int) $tenantId);

            foreach ($mensagens as $m) {
                if (($m->direction ?? null) === 'incoming') {
                    $m->sender_name = $leadName;
                    $m->sender_kind = 'lead';
                } else {
                    if ($hasUserIdColumn && !empty($m->user_id)) {
                        $m->sender_name = $senderNamesByUserId[(string) $m->user_id] ?? $senderNamesByUserId[(int) $m->user_id] ?? $fallbackOutgoingName;
                        $m->sender_kind = 'human';
                    } else {
                        // Mensagem sem user_id = enviada por IA ou Sistema
                        $m->sender_name = $assistantName;
                        $m->sender_kind = 'assistant';
                    }
                }
            }
            
            // Marcar mensagens incoming como lidas
            DB::table('mensagens')
                ->where('conversa_id', $id)
                ->where('direction', 'incoming')
                ->whereNull('read_at')
                ->update(['read_at' => Carbon::now()]);
            
            return response()->json([
                'success' => true,
                'data' => $mensagens
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar mensagens',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function resolveAssistantDisplayName(?int $tenantId = null): string
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if ((!$tenant || empty($tenant->id)) && $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
        }

        if ($tenant) {
            $name = trim((string) $tenant->getAiAssistantName());
            if ($name !== '') {
                return $name;
            }
        }

        $default = env('AI_ASSISTANT_NAME', 'Teresa');
        $name = AppSetting::getValue('ai_name', $default);

        if (is_array($name)) {
            $name = $name['value'] ?? reset($name);
        }

        $name = trim((string) $name);

        return $name !== '' ? $name : $default;
    }
    
    /**
     * Enviar mensagem
     */
    public function enviarMensagem(Request $request, $id)
    {
        try {
            $request->validate([
                'content' => 'required|string|max:4096'
            ]);
            
            $user = $request->user();
            $tenantId = $request->attributes->get('tenant_id');
            
            // Verificar conversa
            $conversa = DB::table('conversas')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$conversa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
            }

            // Corretor só responde atendimentos já distribuídos para ele.
            if ($this->isBrokerRole($user->role ?? null)) {
                if (!$this->brokerCanAccessConversation($user, $conversa)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Você só pode responder conversas atribuídas a você'
                    ], 403);
                }
            } else {
                // Admin (e outros perfis com permissão) tomam a conversa ao enviar
                if (!empty($user?->id)) {
                    DB::table('conversas')
                        ->where('id', $id)
                        ->update([
                            'corretor_id' => $user->id,
                            'updated_at' => Carbon::now(),
                        ]);
                    $conversa->corretor_id = $user->id;
                }
            }
            
            // Criar mensagem
            $payload = [
                'tenant_id' => $tenantId,
                'conversa_id' => $id,
                'direction' => 'outgoing',
                'message_type' => 'text',
                'content' => $request->content,
                'status' => 'queued',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];

            // Se existir coluna user_id, registrar quem enviou
            if (Schema::hasColumn('mensagens', 'user_id')) {
                $payload['user_id'] = $user?->id;
            }

            $mensagemId = DB::table('mensagens')->insertGetId($payload);
            
            // Enviar via provider configurado
            try {
                $driver = strtolower((string) config('whatsapp.driver', 'evolution'));
                if ($driver === 'meta_cloud') {
                    $resultado = app(MetaCloudGateway::class)->sendMessage(
                        $conversa->telefone,
                        $request->content,
                        (int) $tenantId
                    );
                } else {
                    $gateway = $driver === 'evolution'
                        ? app(EvolutionApiService::class)
                        : app(TwilioService::class);

                    $resultado = $gateway->sendMessage(
                        $conversa->telefone,
                        $request->content
                    );
                }

                if (empty($resultado['success'])) {
                    // Twilio respondeu mas não aceitou o envio
                    DB::table('mensagens')
                        ->where('id', $mensagemId)
                        ->update([
                            'status' => 'failed',
                            'updated_at' => Carbon::now()
                        ]);

                    Log::error('Falha ao enviar mensagem via provider WhatsApp (resposta)', [
                        'mensagem_id' => $mensagemId,
                        'to' => $conversa->telefone,
                        'driver' => $driver,
                        'http_code' => $resultado['http_code'] ?? null,
                        'error' => $resultado['error'] ?? null,
                        'response' => $resultado['response'] ?? null,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Falha ao enviar mensagem via provider de WhatsApp',
                        'provider' => [
                            'driver' => $driver,
                            'http_code' => $resultado['http_code'] ?? null,
                            'error' => $resultado['error'] ?? null,
                        ]
                    ], 502);
                }
                
                // Atualizar com message_sid e status
                DB::table('mensagens')
                    ->where('id', $mensagemId)
                    ->update([
                        'message_sid' => $resultado['message_sid'] ?? null,
                        'status' => 'sent',
                        'sent_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                
            } catch (\Exception $e) {
                // Marcar como erro mas não falhar o request
                DB::table('mensagens')
                    ->where('id', $mensagemId)
                    ->update([
                        'status' => 'failed',
                        'updated_at' => Carbon::now()
                    ]);
                
                Log::error('Erro ao enviar via provider de WhatsApp', [
                    'mensagem_id' => $mensagemId,
                    'erro' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao enviar mensagem via provider de WhatsApp'
                ], 502);
            }
            
            // Atualizar última atividade da conversa
            DB::table('conversas')
                ->where('id', $id)
                ->update([
                    'ultima_atividade' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            
            $mensagem = DB::table('mensagens')->find($mensagemId);
            
            return response()->json([
                'success' => true,
                'data' => $mensagem
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar mensagem',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Detalhes de uma conversa
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $tenantId = $request->attributes->get('tenant_id');
            
            $conversa = DB::table('conversas')
                ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                ->leftJoin('users as corretor', 'conversas.corretor_id', '=', 'corretor.id')
                ->select(
                    'conversas.*',
                    'leads.nome as lead_nome',
                    'leads.telefone as lead_telefone',
                    'leads.email as lead_email',
                    'leads.observacoes as lead_observacoes',
                    'leads.classificacao as lead_classificacao',
                    'leads.quartos',
                    'leads.localizacao',
                    'leads.budget_min',
                    'leads.budget_max',
                    'corretor.name as corretor_nome'
                )
                ->where('conversas.id', $id)
                ->where('conversas.tenant_id', $tenantId)
                ->first();
            
            if (!$conversa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
            }

            // Corretor só pode ver detalhes de conversas atribuídas a ele.
            if (!$this->brokerCanAccessConversation($user, $conversa)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar esta conversa'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'data' => $conversa
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar conversa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: atribuir (ou devolver) uma conversa para um corretor/admin.
     * POST /api/admin/conversas/{id}/atribuir
     * Body: { "corretor_id": <int|null> }
     */
    public function atribuirCorretor(Request $request, $id)
    {
        try {
            $user = $request->user();
            $tenantId = $request->attributes->get('tenant_id');

            if (!$user || !$this->isAdminRole($user->role ?? null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Apenas administradores podem atribuir conversas'
                ], 403);
            }

            $request->validate([
                'corretor_id' => 'nullable|integer'
            ]);

            $conversa = DB::table('conversas')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$conversa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
            }

            $targetId = $request->input('corretor_id');
            if ($targetId !== null) {
                $target = DB::table('users')
                    ->where('id', $targetId)
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->first();

                if (!$target) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Usuário alvo não encontrado neste tenant'
                    ], 404);
                }

                if (!in_array($target->role ?? null, ['corretor', 'agent', 'admin', 'super_admin'], true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Usuário alvo inválido para atribuição'
                    ], 422);
                }
            }

            DB::table('conversas')
                ->where('id', $id)
                ->update([
                    'corretor_id' => $targetId,
                    'status' => $targetId ? 'ativa' : 'aguardando_corretor',
                    'updated_at' => Carbon::now(),
                ]);

            if (!empty($conversa->lead_id)) {
                DB::table('leads')
                    ->where('id', $conversa->lead_id)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'corretor_id' => $targetId,
                        'status' => $targetId ? 'em_atendimento' : 'qualificado',
                        'updated_at' => Carbon::now(),
                    ]);
            }

            if ($targetId) {
                $assignedConversation = Conversa::with('lead')->find($id);
                $assignedUser = User::find($targetId);
                if ($assignedConversation && $assignedUser) {
                    app(ConversationAssignmentNotificationService::class)
                        ->notifyAssigned($assignedConversation, $assignedUser, $assignedConversation->lead, $user);
                }
            }

            return response()->json([
                'success' => true,
                'message' => $targetId ? 'Conversa atribuída com sucesso' : 'Conversa devolvida para o pool',
                'data' => DB::table('conversas')->where('id', $id)->first(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atribuir conversa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Proxy para mídias do Twilio (delega ao ConversasController principal)
     */
    public function proxyMedia(Request $request)
    {
        return app(\App\Http\Controllers\ConversasController::class)->proxyMedia($request);
    }
}
