<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $db = app('db');
            $tenantId = $this->resolveTenantId($request);

        $userMessages = DB::raw("(SELECT COUNT(*) FROM mensagens WHERE mensagens.conversa_id = conversas.id AND mensagens.direction = 'incoming') as user_messages_count");
        $totalMessages = DB::raw("(SELECT COUNT(*) FROM mensagens WHERE mensagens.conversa_id = conversas.id) as total_messages");
        $lastMessageSnippet = DB::raw("(SELECT content FROM mensagens WHERE mensagens.conversa_id = conversas.id ORDER BY sent_at DESC LIMIT 1) as ultima_mensagem_text");

        $query = $db->table('conversas')
            ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
            ->select(
                'conversas.*',
                'leads.nome as lead_nome',
                'leads.email as lead_email',
                'leads.whatsapp_name as lead_whatsapp_name',
                'leads.telefone as lead_telefone',
                'leads.status as lead_status',
                'leads.budget_min as lead_budget_min',
                'leads.budget_max as lead_budget_max',
                $userMessages,
                $totalMessages,
                $lastMessageSnippet
            );

            if ($tenantId) {
                $query->where('conversas.tenant_id', $tenantId);
            }
            
            // Filtrar por status
            if ($request->status) {
                $query->where('conversas.status', $request->status);
            }
            
            // Apenas conversas ativas por padrão
            if (!$request->has('all')) {
                $query->where('conversas.status', '!=', 'encerrada');
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
                ->when($tenantId, fn($q) => $q->where('conversas.tenant_id', $tenantId))
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
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->orderBy('sent_at', 'asc')
                ->get();
            
            \Log::info("Encontradas " . count($mensagens) . " mensagens");
            
            // Formatar mensagens para garantir campos corretos
            $mensagens = $mensagens->map(function($msg) {
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
                    'created_at' => $msg->created_at ?? null
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
        $this->validate($request, [
            'content' => 'required|string'
        ]);

        $conversa = $this->resolveConversaForTenant($id, $request);

        $isPortal = str_starts_with($conversa->telefone, 'portal:')
            || str_starts_with($conversa->telefone, 'web:')
            || $conversa->canal === 'portal';

        if ($isPortal) {
            $user = $request->user();
            if ($conversa->corretor_id && $user && $conversa->corretor_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversa em atendimento por outro corretor'
                ], 403);
            }

            $mensagem = Mensagem::create([
                'tenant_id' => $conversa->tenant_id,
                'conversa_id' => $conversa->id,
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
     * Remove conversas vinculadas a um ou mais leads
     * DELETE /api/conversas (payload: lead_ids[])
     */
    public function bulkDestroy(Request $request)
    {
        $data = $this->validate($request, [
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'integer|distinct'
        ]);

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

        $query = Conversa::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->findOrFail($id);
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
}
