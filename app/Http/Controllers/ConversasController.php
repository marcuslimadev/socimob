<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Conversa;
use App\Models\LeadDocument;
use App\Models\LeadPropertyMatch;
use App\Models\Mensagem;
use App\Services\TwilioService;

/**
 * Controller de Conversas
 */
class ConversasController extends Controller
{
    private $twilio;
    
    public function __construct(TwilioService $twilio)
    {
        $this->twilio = $twilio;
    }
    
    /**
     * Listar conversas
     * GET /api/conversas
     */
    public function index(Request $request)
    {
        try {
            $tenantId = $this->resolveTenantId($request);

            // ⚡ Usar Eloquent com eager loading ao invés de Query Builder
            $conversas = Conversa::where('tenant_id', $tenantId)
                ->with(['lead', 'corretor', 'mensagens' => function($query) {
                    $query->orderBy('created_at', 'desc')->limit(1);
                }]) // ⚡ Eager loading para evitar N+1
                ->when($request->status, function($query, $status) {
                    return $query->where('status', $status);
                })
                ->when(!$request->has('all'), function($query) {
                    return $query->where(function ($q) {
                        $q->where('status', '!=', 'encerrada')
                          ->orWhereNull('status');
                    });
                })
                ->orderBy('ultima_atividade', 'desc')
                ->get();

            // Adicionar contadores
            $conversas = $conversas->map(function($conversa) {
                $conversa->user_messages_count = $conversa->mensagens()->where('direction', 'incoming')->count();
                $conversa->total_messages = $conversa->mensagens()->count();
                $conversa->ultima_mensagem_text = $conversa->mensagens->first()->content ?? null;
                return $conversa;
            });

            if ($tenantId) {
                $query->where(function ($q) use ($tenantId) {
                    $q->where('conversas.tenant_id', $tenantId)
                        ->orWhere('leads.tenant_id', $tenantId);
                });
            }
            
            // Filtrar por status
            if ($request->status) {
                $query->where('conversas.status', $request->status);
            }
            
            // Apenas conversas ativas por padrão
            if (!$request->has('all')) {
                $query->where(function ($q) {
                    $q->where('conversas.status', '!=', 'encerrada')
                        ->orWhereNull('conversas.status');
                });
            }
            
            $conversas = $query->orderBy('conversas.ultima_atividade', 'desc')->get();
            
            // Formatar datas para ISO8601 (compatível com JavaScript)
            $conversas = $conversas->map(function($conversa) {
                if ($conversa->iniciada_em) {
                    $conversa->iniciada_em = date('c', strtotime($conversa->iniciada_em));
                }
                if ($conversa->ultima_atividade) {
                    $conversa->ultima_atividade = date('c', strtotime($conversa->ultima_atividade));
                }
                if ($conversa->finalizada_em) {
                    $conversa->finalizada_em = date('c', strtotime($conversa->finalizada_em));
                }
                return $conversa;
            });
            
            return response()->json([
                'success' => true,
                'data' => $conversas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Detalhes da conversa com mensagens
     * GET /api/conversas/{id}
     */
    public function show($id)
    {
        try {
            $db = app('db');
            $tenantId = $this->resolveTenantId(request());
            
            \Log::info("Buscando conversa ID: {$id}");
            
            // Buscar conversa com dados do lead
            $conversa = $db->table('conversas')
                ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                ->leftJoin('users', 'conversas.corretor_id', '=', 'users.id')
                ->select(
                    'conversas.*',
                    'leads.nome as lead_nome',
                    'leads.email as lead_email',
                    'leads.whatsapp_name as lead_whatsapp_name',
                    'users.name as corretor_nome'
                )
                ->where('conversas.id', $id)
                ->when($tenantId, function ($q) use ($tenantId) {
                    $q->where(function ($sub) use ($tenantId) {
                        $sub->where('conversas.tenant_id', $tenantId)
                            ->orWhere('leads.tenant_id', $tenantId);
                    });
                })
                ->first();
            
            if (!$conversa) {
                \Log::warning("Conversa {$id} não encontrada");
                return response()->json([
                    'success' => false,
                    'error' => 'Conversa não encontrada'
                ], 404);
            }
            
            \Log::info("Conversa encontrada, buscando mensagens...");
            
            // Buscar mensagens
            $mensagens = $db->table('mensagens')
                ->where('conversa_id', $id)
                ->orderBy('sent_at', 'asc')
                ->get();
            
            \Log::info("Encontradas " . count($mensagens) . " mensagens");
            
            // Buscar informações dos remetentes (usuários e lead)
            $userIds = $mensagens->pluck('user_id')->filter()->unique();
            $users = $db->table('users')->whereIn('id', $userIds)->get()->keyBy('id');
            
            $lead = null;
            $pessoa = null;
            $leadContext = null;
            
            if ($conversa->lead_id) {
                $lead = $db->table('leads')->find($conversa->lead_id);
                
                // Buscar pessoa associada ao lead
                if ($lead && $lead->pessoa_id) {
                    $pessoa = $db->table('pessoas')->find($lead->pessoa_id);
                }
                
                // Montar contexto do lead para exibição no chat
                if ($lead) {
                    $contextParts = [];
                    
                    // 🏠 IMÓVEL DESEJADO
                    $imovelParts = [];
                    
                    if (!empty($lead->preferencia_tipo_imovel)) {
                        $imovelParts[] = ucfirst($lead->preferencia_tipo_imovel);
                    }
                    
                    if (!empty($lead->preferencia_bairro)) {
                        $imovelParts[] = $lead->preferencia_bairro;
                    }
                    
                    // Cômodos
                    $comodos = [];
                    if (!empty($lead->quartos)) {
                        $comodos[] = $lead->quartos . " qts";
                    }
                    if (!empty($lead->suites)) {
                        $comodos[] = $lead->suites . " suítes";
                    }
                    if (!empty($lead->garagem)) {
                        $comodos[] = $lead->garagem . " vagas";
                    }
                    if (!empty($comodos)) {
                        $imovelParts[] = implode(', ', $comodos);
                    }
                    
                    if (!empty($imovelParts)) {
                        $contextParts[] = "🏠 " . implode(' • ', $imovelParts);
                    }
                    
                    // 💰 ORÇAMENTO E FINANCEIRO
                    $finParts = [];
                    
                    if (!empty($lead->budget_max)) {
                        $budget = (float) $lead->budget_max;
                        if ($budget >= 1000000) {
                            $finParts[] = "R$ " . number_format($budget / 1000000, 1, ',', '.') . "M";
                        } else if ($budget >= 1000) {
                            $finParts[] = "R$ " . number_format($budget / 1000, 0, ',', '.') . "k";
                        } else {
                            $finParts[] = "R$ " . number_format($budget, 0, ',', '.');
                        }
                    }
                    
                    if (!empty($lead->renda_mensal)) {
                        $renda = (float) $lead->renda_mensal;
                        if ($renda >= 1000) {
                            $finParts[] = "Renda: R$ " . number_format($renda / 1000, 1, ',', '.') . "k";
                        }
                    }
                    
                    if (!empty($lead->financiamento_status)) {
                        $statusMap = [
                            'pre_aprovado' => 'Pré-aprovado',
                            'em_analise' => 'Em análise',
                            'a_enviar' => 'A enviar',
                            'nao_precisa' => 'Sem financ.',
                        ];
                        $finParts[] = $statusMap[$lead->financiamento_status] ?? $lead->financiamento_status;
                    }
                    
                    if (!empty($finParts)) {
                        $contextParts[] = "💰 " . implode(' • ', $finParts);
                    }
                    
                    // 👤 PERFIL
                    $perfilParts = [];
                    
                    if (!empty($lead->profissao)) {
                        $perfilParts[] = $lead->profissao;
                    }
                    
                    if (!empty($lead->estado_civil)) {
                        $estadoCivilMap = [
                            'solteiro' => 'Solteiro(a)',
                            'casado' => 'Casado(a)',
                            'divorciado' => 'Divorciado(a)',
                            'viuvo' => 'Viúvo(a)',
                            'uniao_estavel' => 'União estável',
                        ];
                        $perfilParts[] = $estadoCivilMap[$lead->estado_civil] ?? $lead->estado_civil;
                    }
                    
                    if (!empty($lead->composicao_familiar)) {
                        $perfilParts[] = $lead->composicao_familiar;
                    }
                    
                    if (!empty($perfilParts)) {
                        $contextParts[] = "👤 " . implode(' • ', $perfilParts);
                    }
                    
                    // ⏰ PRAZO
                    if (!empty($lead->prazo_compra)) {
                        $prazoMap = [
                            'urgente' => '⚡ Urgente',
                            '1_mes' => '⏰ 1 mês',
                            '3_meses' => '⏰ 3 meses',
                            '6_meses' => '⏰ 6 meses',
                            '1_ano' => '⏰ 1 ano',
                            'sem_pressa' => '⏰ Sem pressa',
                        ];
                        $contextParts[] = $prazoMap[$lead->prazo_compra] ?? "⏰ " . $lead->prazo_compra;
                    }
                    
                    // 📍 STATUS DO LEAD
                    if (!empty($lead->status) && $lead->status !== 'novo') {
                        $statusLabels = [
                            'qualificado' => '✓ Qualificado',
                            'em_negociacao' => '💬 Em negociação',
                            'proposta_enviada' => '📄 Proposta enviada',
                            'ganho' => '🎉 Fechado',
                            'perdido' => '❌ Perdido',
                            'pausado' => '⏸️ Pausado'
                        ];
                        if (isset($statusLabels[$lead->status])) {
                            $contextParts[] = $statusLabels[$lead->status];
                        }
                    }
                    
                    // 📱 ORIGEM
                    if (!empty($lead->fonte)) {
                        $contextParts[] = "📱 " . $lead->fonte;
                    }
                    
                    $leadContext = !empty($contextParts) ? implode(' | ', $contextParts) : null;
                }
            }
            
            // Formatar mensagens para garantir campos corretos
            $mensagens = $mensagens->map(function($msg) use ($users, $lead, $leadContext, $conversa) {
                $senderName = null;
                $senderContext = null;
                
                if ($msg->direction === 'outgoing' && $msg->user_id) {
                    // Mensagem enviada por corretor/admin
                    $user = $users->get($msg->user_id);
                    $senderName = $user ? $user->name : 'Atendente';
                } elseif ($msg->direction === 'incoming') {
                    // Mensagem recebida do lead
                    $senderName = $lead?->nome ?? ($lead?->telefone ?? 'Cliente');
                    $senderContext = $leadContext; // Adicionar contexto do lead
                } else {
                    // Mensagem do sistema/IA
                    $senderName = 'Assistente IA';
                }
                
                return [
                    'id' => $msg->id,
                    'conversa_id' => $msg->conversa_id,
                    'message_sid' => $msg->message_sid ?? null,
                    'direction' => $msg->direction,
                    'message_type' => $msg->message_type,
                    'content' => $msg->content ?? '',
                    'media_url' => $msg->media_url ?? null,
                    'transcription' => $msg->transcription ?? null,
                    'status' => $msg->status ?? 'sent',
                    'sent_at' => $msg->sent_at,
                    'read_at' => $msg->read_at ?? null,
                    'created_at' => $msg->created_at ?? null,
                    'sender_name' => $senderName,
                    'sender_context' => $senderContext,
                    'user_id' => $msg->user_id ?? null
                ];
            });
            
            // Marcar mensagens como lidas
            $db->table('mensagens')
                ->where('conversa_id', $id)
                ->where('direction', 'incoming')
                ->whereNull('read_at')
                ->update(['read_at' => date('Y-m-d H:i:s')]);
            
            \Log::info("Mensagens marcadas como lidas");
            
            // Converter conversa para array e adicionar mensagens
            $conversaArray = (array) $conversa;
            $conversaArray['mensagens'] = $mensagens->toArray();
            
            return response()->json([
                'success' => true,
                'data' => $conversaArray
            ]);
        } catch (\Exception $e) {
            \Log::error("Erro ao buscar conversa {$id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
    
    /**
     * Enviar mensagem manual
     * POST /api/conversas/{id}/mensagens
     */
    public function sendMessage(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $conversa = $this->resolveConversaForTenant($id, $request);
        $user = $request->user();

        // Verificar se conversa está atribuída a outro corretor
        if ($conversa->corretor_id && $user && $conversa->corretor_id !== $user->id) {
            // Apenas admin pode pegar conversa de outro corretor
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversa em atendimento por outro corretor'
                ], 403);
            }
        }

        // Atribuir conversa ao corretor/admin que está enviando a mensagem
        if ($user && empty($conversa->corretor_id)) {
            $conversa->update([
                'corretor_id' => $user->id,
                'user_id' => $user->id,
                'status' => 'em_atendimento',
                'stage' => 'atendimento_humano'
            ]);
            
            Log::info('🔒 Conversa atribuída ao corretor', [
                'conversa_id' => $conversa->id,
                'corretor_id' => $user->id,
                'corretor_nome' => $user->name
            ]);
        }

        $isPortal = str_starts_with($conversa->telefone, 'portal:')
            || str_starts_with($conversa->telefone, 'web:')
            || $conversa->canal === 'portal';

        if ($isPortal) {
            $mensagem = Mensagem::create([
                'tenant_id' => $conversa->tenant_id,
                'conversa_id' => $conversa->id,
                'user_id' => $user?->id,
                'direction' => 'outgoing',
                'message_type' => 'text',
                'content' => $request->input('content'),
                'status' => 'sent',
                'sent_at' => now()
            ]);

            $conversa->update(['ultima_atividade' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Mensagem enviada',
                'data' => $mensagem
            ]);
        }

        // Enviar via Twilio
        $result = $this->twilio->sendMessage($conversa->telefone, $request->input('content'));

        if ($result['success']) {
            // Registrar mensagem
            $mensagem = Mensagem::create([
                'conversa_id' => $conversa->id,
                'user_id' => $user?->id,
                'message_sid' => $result['message_sid'],
                'direction' => 'outgoing',
                'message_type' => 'text',
                'content' => $request->input('content'),
                'status' => 'sent',
                'sent_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mensagem enviada',
                'data' => $mensagem
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Falha ao enviar mensagem'
        ], 500);
    }
    
    /**
     * Conversas ativas em tempo real
     * GET /api/conversas/tempo-real
     */
    public function tempoReal()
    {
        $tenantId = $this->resolveTenantId(request());

        $conversas = Conversa::with(['lead', 'mensagens' => function($q) {
                $q->orderBy('sent_at', 'desc')->limit(1);
            }])
            ->where('status', 'ativa')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('ultima_atividade', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $conversas
        ]);
    }

    /**
     * Buscar conversas por telefone (debug/análise de fluxo IA)
     * GET /api/conversas/por-telefone/{telefone}
     */
    public function porTelefone($telefone)
    {
        try {
            $db = app('db');
            $tenantId = $this->resolveTenantId(request());
            
            // Decodificar URL e normalizar telefone
            $telefone = urldecode($telefone);
            $telefoneNormalizado = preg_replace('/[^0-9]/', '', $telefone);
            
            // Construir formatos possíveis
            $formatosPossiveis = [$telefone, $telefoneNormalizado];
            
            // Adicionar variações com código de país
            // Exemplos: 92992287144 → +5592992287144, +559292287144
            //           9292287144 → +559292287144
            if (strlen($telefoneNormalizado) >= 10 && strlen($telefoneNormalizado) <= 11) {
                $formatosPossiveis[] = '+55' . $telefoneNormalizado;
                $formatosPossiveis[] = 'whatsapp:+55' . $telefoneNormalizado;
            }
            
            // Se já tem 55 no início, adicionar com +
            if (strlen($telefoneNormalizado) >= 12 && str_starts_with($telefoneNormalizado, '55')) {
                $formatosPossiveis[] = '+' . $telefoneNormalizado;
                $formatosPossiveis[] = 'whatsapp:+' . $telefoneNormalizado;
            }
            
            $formatosPossiveis = array_unique($formatosPossiveis);
            
            \Log::info("Buscando conversas para telefone", [
                'telefone_original' => $telefone,
                'telefone_normalizado' => $telefoneNormalizado,
                'formatos_busca' => $formatosPossiveis,
                'sufixo_8_digitos' => strlen($telefoneNormalizado) >= 8 ? substr($telefoneNormalizado, -8) : null
            ]);
            
            // Buscar conversas - tentar com e sem whatsapp:
            $conversas = $db->table('conversas')
                ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                ->leftJoin('users', 'conversas.corretor_id', '=', 'users.id')
                ->select(
                    'conversas.*',
                    'leads.nome as lead_nome',
                    'leads.email as lead_email',
                    'leads.whatsapp_name as lead_whatsapp_name',
                    'leads.telefone as lead_telefone',
                    'leads.budget_min as lead_budget_min',
                    'leads.budget_max as lead_budget_max',
                    'leads.localizacao as lead_localizacao',
                    'leads.quartos as lead_quartos',
                    'leads.suites as lead_suites',
                    'leads.garagem as lead_garagem',
                    'leads.caracteristicas_desejadas as lead_caracteristicas',
                    'leads.status as lead_status',
                    'leads.score as lead_score',
                    'users.nome as corretor_nome'
                )
                ->where(function($query) use ($formatosPossiveis, $telefoneNormalizado) {
                    foreach ($formatosPossiveis as $formato) {
                        $query->orWhere('conversas.telefone', $formato);
                    }
                    // Buscar pelos últimos 8 dígitos (sufixo único do número)
                    if (strlen($telefoneNormalizado) >= 8) {
                        $sufixo = substr($telefoneNormalizado, -8);
                        $query->orWhere('conversas.telefone', 'LIKE', '%' . $sufixo);
                    }
                })
                ->when($tenantId, fn($q) => $q->where('conversas.tenant_id', $tenantId))
                ->orderBy('conversas.iniciada_em', 'desc')
                ->get();
            
            \Log::info("Conversas encontradas", [
                'total' => count($conversas),
                'telefones' => $conversas->pluck('telefone')->toArray()
            ]);
            
            // Para cada conversa, buscar mensagens
            $resultado = $conversas->map(function($conversa) use ($db, $tenantId) {
                $mensagens = $db->table('mensagens')
                    ->where('conversa_id', $conversa->id)
                    ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->orderBy('sent_at', 'desc') // Mais recentes primeiro
                    ->get()
                    ->map(function($msg) {
                        return [
                            'id' => $msg->id,
                            'direction' => $msg->direction,
                            'message_type' => $msg->message_type,
                            'content' => $msg->content ?? '',
                            'media_url' => $msg->media_url ?? null,
                            'transcription' => $msg->transcription ?? null,
                            'status' => $msg->status ?? 'sent',
                            'sent_at' => $msg->sent_at,
                            'created_at' => $msg->created_at ?? null
                        ];
                    });
                
                return [
                    'id' => $conversa->id,
                    'telefone' => $conversa->telefone,
                    'status' => $conversa->status,
                    'iniciada_em' => $conversa->iniciada_em,
                    'ultima_atividade' => $conversa->ultima_atividade,
                    'finalizada_em' => $conversa->finalizada_em,
                    'lead' => [
                        'id' => $conversa->lead_id,
                        'nome' => $conversa->lead_nome,
                        'email' => $conversa->lead_email,
                        'whatsapp_name' => $conversa->lead_whatsapp_name,
                        'telefone' => $conversa->lead_telefone,
                        'budget_min' => $conversa->lead_budget_min,
                        'budget_max' => $conversa->lead_budget_max,
                        'localizacao' => $conversa->lead_localizacao,
                        'quartos' => $conversa->lead_quartos,
                        'suites' => $conversa->lead_suites,
                        'garagem' => $conversa->lead_garagem,
                        'caracteristicas_desejadas' => $conversa->lead_caracteristicas,
                        'status' => $conversa->lead_status,
                        'score' => $conversa->lead_score
                    ],
                    'corretor' => [
                        'id' => $conversa->corretor_id,
                        'nome' => $conversa->corretor_nome
                    ],
                    'total_mensagens' => count($mensagens),
                    'mensagens' => $mensagens->toArray()
                ];
            });
            
            return response()->json([
                'success' => true,
                'telefone_busca' => $telefone,
                'telefone_normalizado' => $telefoneNormalizado,
                'formatos_testados' => $formatosPossiveis,
                'sufixo_8_digitos' => strlen($telefoneNormalizado) >= 8 ? substr($telefoneNormalizado, -8) : null,
                'total_conversas' => count($resultado),
                'data' => $resultado->toArray()
            ]);
            
        } catch (\Exception $e) {
            \Log::error("Erro ao buscar conversas por telefone: " . $e->getMessage(), [
                'telefone' => $telefone,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Remove uma conversa específica
     * DELETE /api/conversas/{id}
     */
    public function destroy($id)
    {
        $conversa = $this->resolveConversaForTenant($id, request());

        DB::beginTransaction();

        try {
            $stats = $this->deleteConversas([$conversa->id]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Conversa excluída com sucesso',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Redesignar conversa para outro corretor (admin only)
     * POST /api/conversas/{id}/assign
     */
    public function assign(Request $request, $id)
    {
        $user = $request->user();
        
        // Apenas admins podem redesignar
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado. Apenas administradores podem redesignar conversas.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'corretor_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $conversa = $this->resolveConversaForTenant($id, $request);
        $corretorId = $request->input('corretor_id');

        // Se corretor_id é null, libera a conversa para a IA
        if ($corretorId === null) {
            $conversa->update([
                'corretor_id' => null,
                'user_id' => null,
                'status' => 'ativa',
                'stage' => 'inicial'
            ]);

            \Log::info('🔓 Conversa liberada para IA', [
                'conversa_id' => $conversa->id,
                'admin' => $user->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conversa liberada para IA',
                'data' => $conversa
            ]);
        }

        // Redesignar para outro corretor
        $conversa->update([
            'corretor_id' => $corretorId,
            'user_id' => $corretorId,
            'status' => 'em_atendimento',
            'stage' => 'atendimento_humano'
        ]);

        \Log::info('🔄 Conversa redesignada', [
            'conversa_id' => $conversa->id,
            'novo_corretor_id' => $corretorId,
            'admin' => $user->name
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conversa redesignada com sucesso',
            'data' => $conversa
        ]);
    }

    /**
     * Remove conversas vinculadas a um ou mais leads
     * DELETE /api/conversas (payload: lead_ids[])
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'integer|distinct'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $tenantId = $this->resolveTenantId($request);

        $conversaIds = Conversa::whereIn('lead_id', $data['lead_ids'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->pluck('id')
            ->all();

        if (empty($conversaIds)) {
            return response()->json([
                'success' => false,
                'error' => 'Nenhuma conversa encontrada para os leads informados'
            ], 404);
        }

        DB::beginTransaction();

        try {
            $stats = $this->deleteConversas($conversaIds);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Conversas excluídas com sucesso',
                'data' => array_merge($stats, ['conversa_ids' => $conversaIds])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function resolveConversaForTenant($id, Request $request): Conversa
    {
        $tenantId = $this->resolveTenantId($request);

        $query = Conversa::query()
            ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
            ->select('conversas.*')
            ->where('conversas.id', $id);

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('conversas.tenant_id', $tenantId)
                    ->orWhere('leads.tenant_id', $tenantId);
            });
        }

        return $query->firstOrFail();
    }

    private function resolveTenantId(Request $request): ?int
    {
        $resolver = $request->getUserResolver();
        if ($resolver) {
            $user = $resolver();
            if ($user && !empty($user->tenant_id)) {
                return (int) $user->tenant_id;
            }
        }

        if ($request->attributes->has('tenant_id')) {
            return (int) $request->attributes->get('tenant_id');
        }

        if (app()->bound('tenant')) {
            return (int) app('tenant')->id;
        }

        return null;
    }

    private function deleteConversas(array $conversaIds): array
    {
        if (empty($conversaIds)) {
            return [
                'conversas' => 0,
                'mensagens' => 0,
                'documentos' => 0,
                'matches_atualizados' => 0,
            ];
        }

        $mensagensDeletadas = Mensagem::whereIn('conversa_id', $conversaIds)->delete();
        $documentosDeletados = LeadDocument::whereIn('conversa_id', $conversaIds)->delete();

        $matchesAtualizados = LeadPropertyMatch::whereIn('conversa_id', $conversaIds)
            ->update(['conversa_id' => null]);

        $conversasDeletadas = Conversa::whereIn('id', $conversaIds)->delete();

        return [
            'conversas' => $conversasDeletadas,
            'mensagens' => $mensagensDeletadas,
            'documentos' => $documentosDeletados,
            'matches_atualizados' => $matchesAtualizados,
        ];
    }

    /**
     * Proxy para mídias do Twilio (evita erro de autenticação no frontend)
     */
    public function proxyMedia(Request $request)
    {
        $mediaUrl = $request->query('url');

        if (!$mediaUrl) {
            return response()->json(['error' => 'URL da mídia não fornecida'], 400);
        }

        // Validar que é uma URL do Twilio
        if (!str_contains($mediaUrl, 'twilio.com')) {
            return response()->json(['error' => 'URL inválida'], 403);
        }

        try {
            $result = $this->twilio->downloadMedia($mediaUrl);

            if ($result['success']) {
                $contentType = $result['contentType'] ?? 'application/octet-stream';

                // Limpar parâmetros extras do content-type (ex: "image/jpeg; charset=UTF-8")
                $cleanType = explode(';', $contentType)[0];

                return response($result['data'], 200)
                    ->header('Content-Type', $cleanType)
                    ->header('Cache-Control', 'public, max-age=86400')
                    ->header('X-Media-Content-Type', $cleanType); // Header extra para debug no frontend
            }

            return response()->json([
                'error' => $result['error'] ?? 'Falha ao baixar mídia',
            ], 502);
        } catch (\Exception $e) {
            \Log::error("Erro ao fazer proxy de mídia: " . $e->getMessage());
            return response()->json(['error' => 'Erro ao processar mídia'], 500);
        }
    }
}
