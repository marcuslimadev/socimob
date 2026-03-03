<?php
namespace App\Http\Controllers;

use App\Models\Conversa;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CRMController extends Controller
{
    /**
     * Listar clientes do CRM agrupados por status
     * GET /api/crm/clientes
     */
    public function index(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');
            $user = $request->user();

            $query = Lead::with(['pessoa:id,nome,tipo,cpf,email,telefone,celular,observacoes,origem', 'corretor:id,name'])
                ->where('tenant_id', $tenantId);

            // Permissoes: corretor ve so os seus + livres
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                $query->where(function ($q) use ($user) {
                    $q->where('corretor_id', $user->id)
                      ->orWhereNull('corretor_id');
                });
            }

            // Filtros
            if ($request->search) {
                $s = $request->search;
                $query->where(function ($q) use ($s) {
                    $q->where('nome', 'like', "%{$s}%")
                      ->orWhere('telefone', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%");
                });
            }
            if ($request->corretor_id) {
                $query->where('corretor_id', $request->corretor_id);
            }
            if ($request->classificacao) {
                $query->where('classificacao', $request->classificacao);
            }
            if ($request->status) {
                $query->where('status', $request->status);
            }

            $sortBy = $request->get('sort_by', 'updated_at');
            $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            $allowedSort = ['updated_at', 'nome', 'status'];
            if (!in_array($sortBy, $allowedSort, true)) {
                $sortBy = 'updated_at';
            }

            if ($request->boolean('flat')) {
                $perPage = (int) $request->get('per_page', 50);
                $perPage = max(10, min(200, $perPage));

                $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);
                $leads = collect($paginator->items());

                $leadIds = $leads->pluck('id');
                $conversas = Conversa::whereIn('lead_id', $leadIds)
                    ->orderBy('ultima_atividade', 'desc')
                    ->get()
                    ->groupBy('lead_id');

                $conversaIds = $conversas->flatten()->pluck('id');

                $lastMessages = collect();
                if ($conversaIds->isNotEmpty()) {
                    $lastMessages = DB::table('mensagens')
                        ->whereIn('conversa_id', $conversaIds)
                        ->whereIn('id', function ($q) use ($conversaIds) {
                            $q->selectRaw('MAX(id)')
                              ->from('mensagens')
                              ->whereIn('conversa_id', $conversaIds)
                              ->groupBy('conversa_id');
                        })
                        ->get()
                        ->keyBy('conversa_id');
                }

                $unreadCounts = collect();
                if ($conversaIds->isNotEmpty()) {
                    $unreadCounts = DB::table('mensagens')
                        ->whereIn('conversa_id', $conversaIds)
                        ->where('direction', 'incoming')
                        ->where(function ($q) {
                            $q->whereNull('read_at')
                              ->orWhere('read_at', '');
                        })
                        ->selectRaw('conversa_id, count(*) as count')
                        ->groupBy('conversa_id')
                        ->pluck('count', 'conversa_id');
                }

                $result = $leads->map(function ($lead) use ($conversas, $lastMessages, $unreadCounts) {
                    $conversa = $conversas->get($lead->id)?->first();
                    $conversaId = $conversa?->id;
                    $lastMsg = $conversaId ? $lastMessages->get($conversaId) : null;
                    $unread = $conversaId ? ($unreadCounts[$conversaId] ?? 0) : 0;
                    return [
                        'id' => $lead->id,
                        'pessoa_id' => $lead->pessoa_id,
                        'nome' => $lead->nome,
                        'telefone' => $lead->telefone,
                        'email' => $lead->email,
                        'status' => $lead->status ?? 'novo',
                        'classificacao' => $lead->classificacao,
                        'observacoes' => $lead->observacoes ?: ($lead->pessoa?->observacoes ?? null),
                        'observacoes_cliente' => $lead->observacoes_cliente,
                        'valor' => $lead->budget_max ?? $lead->budget_min,
                        'corretor_id' => $lead->corretor_id,
                        'corretor_nome' => $lead->corretor?->name,
                        'pessoa' => $lead->pessoa,
                        'conversa_id' => $conversaId,
                        'ultima_mensagem' => $lastMsg?->content,
                        'ultima_mensagem_at' => $lastMsg?->created_at,
                        'unread' => (int) $unread,
                        'origem' => $lead->origem ?: ($lead->pessoa?->origem ?? null),
                        'sms_enviado' => (bool) $lead->sms_enviado,
                        'updated_at' => $lead->updated_at?->toIso8601String(),
                        'created_at' => $lead->created_at?->toIso8601String(),
                    ];
                });

                return response()->json([
                    'success' => true,
                    'data' => $result,
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ]);
            }

            $leads = $query->orderBy('updated_at', 'desc')->get();

            // Batch load conversas + last message + unread count
            $leadIds = $leads->pluck('id');

            $conversas = Conversa::whereIn('lead_id', $leadIds)
                ->orderBy('ultima_atividade', 'desc')
                ->get()
                ->groupBy('lead_id');

            $conversaIds = $conversas->flatten()->pluck('id');

            // Last message per conversa
            $lastMessages = collect();
            if ($conversaIds->isNotEmpty()) {
                $lastMessages = DB::table('mensagens')
                    ->whereIn('conversa_id', $conversaIds)
                    ->whereIn('id', function ($q) use ($conversaIds) {
                        $q->selectRaw('MAX(id)')
                          ->from('mensagens')
                          ->whereIn('conversa_id', $conversaIds)
                          ->groupBy('conversa_id');
                    })
                    ->get()
                    ->keyBy('conversa_id');
            }

            // Unread counts
            $unreadCounts = collect();
            if ($conversaIds->isNotEmpty()) {
                $unreadCounts = DB::table('mensagens')
                    ->whereIn('conversa_id', $conversaIds)
                    ->where('direction', 'incoming')
                    ->where(function ($q) {
                        $q->whereNull('read_at')
                          ->orWhere('read_at', '');
                    })
                    ->selectRaw('conversa_id, count(*) as count')
                    ->groupBy('conversa_id')
                    ->pluck('count', 'conversa_id');
            }

            // Map results
            $result = $leads->map(function ($lead) use ($conversas, $lastMessages, $unreadCounts) {
                $conversa = $conversas->get($lead->id)?->first();
                $conversaId = $conversa?->id;
                $lastMsg = $conversaId ? $lastMessages->get($conversaId) : null;
                $unread = $conversaId ? ($unreadCounts[$conversaId] ?? 0) : 0;

                return [
                    'id' => $lead->id,
                    'pessoa_id' => $lead->pessoa_id,
                    'nome' => $lead->nome,
                    'telefone' => $lead->telefone,
                    'email' => $lead->email,
                    'status' => $lead->status ?? 'novo',
                    'classificacao' => $lead->classificacao,
                    'observacoes' => $lead->observacoes ?: ($lead->pessoa?->observacoes ?? null),
                    'observacoes_cliente' => $lead->observacoes_cliente,
                    'valor' => $lead->budget_max ?? $lead->budget_min,
                    'corretor_id' => $lead->corretor_id,
                    'corretor_nome' => $lead->corretor?->name,
                    'pessoa' => $lead->pessoa,
                    'conversa_id' => $conversaId,
                    'ultima_mensagem' => $lastMsg?->content,
                    'ultima_mensagem_at' => $lastMsg?->created_at,
                    'unread' => (int) $unread,
                    'origem' => $lead->origem ?: ($lead->pessoa?->origem ?? null),
                    'sms_enviado' => (bool) $lead->sms_enviado,
                    'updated_at' => $lead->updated_at?->toIso8601String(),
                    'created_at' => $lead->created_at?->toIso8601String(),
                ];
            });

            // Agrupar por status
            $statuses = ['novo', 'em_atendimento', 'qualificado', 'proposta', 'fechado', 'perdido'];
            $grouped = [];
            foreach ($statuses as $status) {
                $grouped[$status] = $result->where('status', $status)->values();
            }

            return response()->json([
                'success' => true,
                'data' => $grouped,
                'total' => $result->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('[CRM] Erro ao listar clientes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar clientes',
            ], 500);
        }
    }

    /**
     * Atualizar status do lead (drag-and-drop ou botao)
     * PATCH /api/crm/clientes/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id') ?? $request->user()?->tenant_id;

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:novo,em_atendimento,qualificado,proposta,fechado,perdido',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Status inválido',
                    'messages' => $validator->errors(),
                ], 422);
            }

            if (!$tenantId) {
                return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 403);
            }
            $lead = Lead::where('tenant_id', $tenantId)->findOrFail($id);
            $lead->status = $request->status;
            $lead->updated_at = now();
            $lead->save();

            return response()->json([
                'success' => true,
                'message' => 'Status atualizado',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Cliente não encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('[CRM] Erro ao atualizar status', [
                'lead_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar status',
            ], 500);
        }
    }
}
