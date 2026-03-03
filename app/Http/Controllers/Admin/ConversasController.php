<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class ConversasController extends Controller
{
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
                    'corretor.name as corretor_nome'
                )
                ->where(function ($q) use ($tenantId) {
                    // Compat: alguns registros antigos podem ter conversas.tenant_id nulo
                    $q->where('conversas.tenant_id', $tenantId)
                      ->orWhere('leads.tenant_id', $tenantId);
                });
            
            // Se for corretor, buscar suas conversas + todas as conversas ainda não atendidas (sem outgoing)
            if ($user->role === 'corretor') {
                // Buscar conversas do corretor
                $minhasConversas = DB::table('conversas')
                    ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                    ->leftJoin('users as corretor', 'conversas.corretor_id', '=', 'corretor.id')
                    ->select(
                        'conversas.*',
                        'leads.nome as lead_nome',
                        'leads.telefone as lead_telefone',
                        'leads.email as lead_email',
                        'corretor.name as corretor_nome'
                    )
                    ->where(function ($q) use ($tenantId) {
                        $q->where('conversas.tenant_id', $tenantId)
                          ->orWhere('leads.tenant_id', $tenantId);
                    })
                    ->where('conversas.corretor_id', $user->id)
                    ->orderBy('conversas.ultima_atividade', 'desc')
                    ->get();

                // Conversas disponíveis para todos os corretores: ainda não atendidas por humano (e nem pela IA)
                // Regra: ainda não existe mensagem outgoing enviada por um usuário humano.
                // Mensagens automáticas/IA não possuem user_id e NÃO removem do pool.
                $hasUserIdColumn = Schema::hasColumn('mensagens', 'user_id');
                if ($hasUserIdColumn) {
                    // Novo comportamento: só tira do pool quando houver outgoing humano (mensagens.user_id preenchido).
                    $conversasNaoAtendidas = DB::table('conversas')
                        ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                        ->leftJoin('users as corretor', 'conversas.corretor_id', '=', 'corretor.id')
                        ->select(
                            'conversas.*',
                            'leads.nome as lead_nome',
                            'leads.telefone as lead_telefone',
                            'leads.email as lead_email',
                            'corretor.name as corretor_nome'
                        )
                        ->where(function ($q) use ($tenantId) {
                            $q->where('conversas.tenant_id', $tenantId)
                              ->orWhere('leads.tenant_id', $tenantId);
                        })
                        ->whereNull('conversas.corretor_id')
                        ->where('conversas.status', 'ativa')
                        ->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('mensagens')
                                ->whereColumn('mensagens.conversa_id', 'conversas.id')
                                ->where('mensagens.direction', 'outgoing')
                                ->whereNotNull('mensagens.user_id');
                        })
                        ->orderBy('conversas.created_at', 'asc')
                        ->get();
                } else {
                    // Fallback antigo (se não houver coluna user_id): qualquer outgoing tira do pool.
                    $conversasNaoAtendidas = DB::table('conversas')
                        ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                        ->leftJoin('users as corretor', 'conversas.corretor_id', '=', 'corretor.id')
                        ->select(
                            'conversas.*',
                            'leads.nome as lead_nome',
                            'leads.telefone as lead_telefone',
                            'leads.email as lead_email',
                            'corretor.name as corretor_nome'
                        )
                        ->where(function ($q) use ($tenantId) {
                            $q->where('conversas.tenant_id', $tenantId)
                              ->orWhere('leads.tenant_id', $tenantId);
                        })
                        ->whereNull('conversas.corretor_id')
                        ->where('conversas.status', 'ativa')
                        ->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('mensagens')
                                ->whereColumn('mensagens.conversa_id', 'conversas.id')
                                ->where('mensagens.direction', 'outgoing');
                        })
                        ->orderBy('conversas.created_at', 'asc')
                        ->get();
                }

                // Minhas conversas primeiro; depois as não atendidas (pool)
                $conversas = $minhasConversas
                    ->concat($conversasNaoAtendidas)
                    ->unique('id')
                    ->values();
            } else {
                // Admin vê suas conversas + conversas livres (sem corretor_id)
                $userId = $user->id;
                $conversas = $query
                    ->where(function ($q) use ($userId) {
                        $q->whereNull('conversas.corretor_id')
                          ->orWhere('conversas.corretor_id', $userId);
                    })
                    ->orderBy('conversas.ultima_atividade', 'desc')
                    ->orderBy('conversas.created_at', 'asc')
                    ->get();
            }
            
            // Verificar se coluna message_type existe (uma vez, fora do loop)
            $hasMsgTypeColumn = false;
            try {
                $hasMsgTypeColumn = Schema::hasColumn('mensagens', 'message_type');
            } catch (\Exception $e) {
                Log::warning('Erro ao verificar coluna message_type', ['error' => $e->getMessage()]);
            }
            
            // Adicionar informações extras
            foreach ($conversas as &$conversa) {
                $conversa->lead_nome = $this->sanitizeUtf8($conversa->lead_nome ?? null);
                $conversa->lead_email = $this->sanitizeUtf8($conversa->lead_email ?? null);
                $conversa->corretor_nome = $this->sanitizeUtf8($conversa->corretor_nome ?? null);

                // Última mensagem
                $ultimaMensagem = DB::table('mensagens')
                    ->where('conversa_id', $conversa->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                $conversa->ultima_mensagem = $ultimaMensagem 
                    ? $this->sanitizeUtf8(substr((string) $ultimaMensagem->content, 0, 100)) 
                    : null;
                
                // Contar não lidas (incoming que não têm read_at)
                $conversa->mensagens_nao_lidas = DB::table('mensagens')
                    ->where('conversa_id', $conversa->id)
                    ->where('direction', 'incoming')
                    ->whereNull('read_at')
                    ->count();
                
                // Indicar se está em fila ou atribuída
                $conversa->em_fila = is_null($conversa->corretor_id);
                $conversa->atribuida_a_mim = $conversa->corretor_id == $user->id;
                
                // Verificar se precisa de intervenção humana (SMS enviado há 2h+ sem resposta)
                $conversa->needs_human_intervention = false;
                try {
                    $query = DB::table('mensagens')
                        ->where('conversa_id', $conversa->id)
                        ->where('direction', 'outgoing')
                        ->whereNotNull('sent_at');
                    
                    // Se coluna message_type existe, filtrar por SMS
                    if ($hasMsgTypeColumn) {
                        $query->where('message_type', 'sms');
                    }
                    
                    $ultimoSms = $query->orderBy('sent_at', 'desc')->first();
                    
                    if ($ultimoSms) {
                        $respostaCliente = DB::table('mensagens')
                            ->where('conversa_id', $conversa->id)
                            ->where('direction', 'incoming')
                            ->where('created_at', '>', $ultimoSms->sent_at)
                            ->exists();
                        
                        // Se não houve resposta e passaram 2+ horas
                        if (!$respostaCliente) {
                            $horas = Carbon::parse($ultimoSms->sent_at)->diffInHours(Carbon::now());
                            if ($horas >= 2) {
                                $conversa->needs_human_intervention = true;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Erro ao verificar needs_human_intervention', [
                        'conversa_id' => $conversa->id,
                        'error' => $e->getMessage()
                    ]);
                }
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
        try {
            $user = $request->user();
            $tenantId = $request->attributes->get('tenant_id');
            
            // Apenas corretores podem pegar conversas da fila
            if ($user->role !== 'corretor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Apenas corretores podem pegar conversas da fila'
                ], 403);
            }
            
            // Buscar próxima conversa em fila (FIFO - primeiro que chegou)
            $conversa = DB::table('conversas')
                ->where('tenant_id', $tenantId)
                ->whereNull('corretor_id')
                ->where('status', 'ativa')
                ->orderBy('created_at', 'asc') // Primeira a chegar
                ->first();
            
            if (!$conversa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não há conversas na fila no momento'
                ], 404);
            }
            
            // Atribuir ao corretor
            DB::table('conversas')
                ->where('id', $conversa->id)
                ->update([
                    'corretor_id' => $user->id,
                    'updated_at' => Carbon::now()
                ]);
            
            // Registrar log
            \App\Models\SystemLog::info(
                \App\Models\SystemLog::CATEGORY_AUTOMATION,
                'conversa_atribuida',
                'Conversa atribuída automaticamente ao corretor',
                [
                    'conversa_id' => $conversa->id,
                    'corretor_id' => $user->id,
                    'corretor_nome' => $user->name,
                    'lead_id' => $conversa->lead_id,
                    'tenant_id' => $tenantId
                ]
            );
            
            // Buscar dados completos
            $conversaCompleta = DB::table('conversas')
                ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                ->select('conversas.*', 'leads.nome as lead_nome', 'leads.telefone as lead_telefone')
                ->where('conversas.id', $conversa->id)
                ->first();
            
            return response()->json([
                'success' => true,
                'message' => 'Conversa atribuída com sucesso',
                'data' => $conversaCompleta
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao pegar conversa da fila',
                'error' => $e->getMessage()
            ], 500);
        }
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
            if ($user->role === 'corretor' && $conversa->corretor_id != $user->id) {
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
                    'updated_at' => Carbon::now()
                ]);
            
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
            
            $stats = [
                'em_fila' => DB::table('conversas')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('corretor_id')
                    ->where('status', 'ativa')
                    ->count(),
                    
                'atribuidas' => DB::table('conversas')
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('corretor_id')
                    ->where('status', 'ativa')
                    ->count(),
                    
                'total_ativas' => DB::table('conversas')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'ativa')
                    ->count(),
                    
                'por_corretor' => DB::table('conversas')
                    ->join('users', 'conversas.corretor_id', '=', 'users.id')
                    ->where('conversas.tenant_id', $tenantId)
                    ->where('conversas.status', 'ativa')
                    ->whereNotNull('conversas.corretor_id')
                    ->select('users.name', DB::raw('COUNT(*) as total'))
                    ->groupBy('users.id', 'users.name')
                    ->get()
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

            // Corretor só pode ver mensagens se a conversa estiver livre ou atribuída a ele
            if (($user->role ?? null) === 'corretor' && !empty($conversa->corretor_id) && (int) $conversa->corretor_id !== (int) $user->id) {
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

            foreach ($mensagens as $m) {
                if (($m->direction ?? null) === 'incoming') {
                    $m->sender_name = $leadName;
                } else {
                    if ($hasUserIdColumn && !empty($m->user_id)) {
                        $m->sender_name = $senderNamesByUserId[(string) $m->user_id] ?? $senderNamesByUserId[(int) $m->user_id] ?? $fallbackOutgoingName;
                    } else {
                        // Mensagem sem user_id = enviada por IA ou Sistema
                        $m->sender_name = 'Assistente IA';
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
    
    /**
     * Enviar mensagem
     */
    public function enviarMensagem(Request $request, $id)
    {
        try {
            $request->validate([
                'content' => 'required|string'
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

            // Regras de atribuição:
            // - Corretor: ao enviar a primeira mensagem, a conversa deve sumir para os outros (claim). Se já estiver atribuída a outro, bloquear.
            // - Admin: ao enviar, o atendimento vai pra ele imediatamente (toma a conversa).
            if (($user->role ?? null) === 'corretor') {
                if (!empty($conversa->corretor_id) && (int) $conversa->corretor_id !== (int) $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Conversa já está em atendimento por outro corretor'
                    ], 403);
                }

                if (empty($conversa->corretor_id)) {
                    // Claim atômico: só toma se ainda estiver livre
                    $updated = DB::table('conversas')
                        ->where('id', $id)
                        ->whereNull('corretor_id')
                        ->update([
                            'corretor_id' => $user->id,
                            'updated_at' => Carbon::now(),
                        ]);

                    if ($updated === 0) {
                        // Alguém tomou antes
                        $currentOwner = DB::table('conversas')->where('id', $id)->value('corretor_id');
                        if (!empty($currentOwner) && (int) $currentOwner !== (int) $user->id) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Conversa foi atribuída a outro usuário. Atualize a lista.'
                            ], 409);
                        }
                    }

                    // Atualizar objeto local
                    $conversa->corretor_id = $user->id;
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
            
            // Enviar via Twilio
            try {
                $twilioService = app(\App\Services\TwilioService::class);
                $resultado = $twilioService->sendMessage(
                    $conversa->telefone,
                    $request->content
                );

                if (empty($resultado['success'])) {
                    // Twilio respondeu mas não aceitou o envio
                    DB::table('mensagens')
                        ->where('id', $mensagemId)
                        ->update([
                            'status' => 'failed',
                            'updated_at' => Carbon::now()
                        ]);

                    \Illuminate\Support\Facades\Log::error('Falha ao enviar via Twilio (resposta)', [
                        'mensagem_id' => $mensagemId,
                        'to' => $conversa->telefone,
                        'http_code' => $resultado['http_code'] ?? null,
                        'error' => $resultado['error'] ?? null,
                        'response' => $resultado['response'] ?? null,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Falha ao enviar mensagem via Twilio',
                        'twilio' => [
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
                
                \Illuminate\Support\Facades\Log::error('Erro ao enviar via Twilio', [
                    'mensagem_id' => $mensagemId,
                    'erro' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao enviar mensagem via Twilio'
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

            // Corretor só pode ver detalhes se a conversa estiver livre ou atribuída a ele
            if (($user->role ?? null) === 'corretor' && !empty($conversa->corretor_id) && (int) $conversa->corretor_id !== (int) $user->id) {
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

            if (!$user || ($user->role ?? null) !== 'admin') {
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
                    ->first();

                if (!$target) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Usuário alvo não encontrado neste tenant'
                    ], 404);
                }

                if (!in_array($target->role ?? null, ['corretor', 'admin'], true)) {
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
                    'updated_at' => Carbon::now(),
                ]);

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
