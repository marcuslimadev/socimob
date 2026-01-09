<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ConversasController extends BaseController
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
                    'corretor.name as corretor_nome'
                )
                ->where(function ($q) use ($tenantId) {
                    // Compat: alguns registros antigos podem ter conversas.tenant_id nulo
                    $q->where('conversas.tenant_id', $tenantId)
                      ->orWhere('leads.tenant_id', $tenantId);
                });
            
            // Se for corretor, buscar suas conversas E uma da fila
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
                
                // Buscar UMA conversa da fila (FIFO - mais antiga primeiro)
                $conversaDaFila = DB::table('conversas')
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
                    ->orderBy('conversas.created_at', 'asc') // FIFO
                    ->first();
                
                // Juntar minhas conversas + 1 da fila
                $conversas = $minhasConversas;
                if ($conversaDaFila) {
                    $conversas->push($conversaDaFila);
                }
            } else {
                // Admin vê tudo
                $conversas = $query->orderBy('conversas.ultima_atividade', 'desc')
                    ->orderBy('conversas.created_at', 'asc')
                    ->get();
            }
            
            // Adicionar informações extras
            foreach ($conversas as &$conversa) {
                // Última mensagem
                $ultimaMensagem = DB::table('mensagens')
                    ->where('conversa_id', $conversa->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                $conversa->ultima_mensagem = $ultimaMensagem 
                    ? substr($ultimaMensagem->content, 0, 100) 
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
            }
            
            return response()->json([
                'success' => true,
                'data' => $conversas
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar conversas',
                'error' => $e->getMessage()
            ], 500);
        }
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
                    $senderName = null;
                    if ($hasUserIdColumn && !empty($m->user_id)) {
                        $senderName = $senderNamesByUserId[(string) $m->user_id] ?? $senderNamesByUserId[(int) $m->user_id] ?? null;
                    }
                    $m->sender_name = $senderName ?: $fallbackOutgoingName;
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
            $this->validate($request, [
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
                
                // Atualizar com message_sid e status
                DB::table('mensagens')
                    ->where('id', $mensagemId)
                    ->update([
                        'message_sid' => $resultado['sid'] ?? null,
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
            $tenantId = $request->attributes->get('tenant_id');
            
            $conversa = DB::table('conversas')
                ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                ->leftJoin('users as corretor', 'conversas.corretor_id', '=', 'corretor.id')
                ->select(
                    'conversas.*',
                    'leads.nome as lead_nome',
                    'leads.telefone as lead_telefone',
                    'leads.email as lead_email',
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
}
