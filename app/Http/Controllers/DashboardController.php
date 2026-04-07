<?php
namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Conversa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller do Dashboard
 */
class DashboardController extends Controller
{
    /**
     * Estatísticas gerais
     * GET /api/dashboard/stats
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user();
            $tenantId = $user->tenant_id ?? ($request->attributes->get('tenant_id') ?? null);

            if (!$tenantId) {
                return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 403);
            }

            $db = app('db');
            $hasProperties = Schema::hasTable('imo_properties');
            $imoveis = ['total' => 0, 'ativos' => 0];
            if ($hasProperties) {
                $imoveis['total'] = $db->table('imo_properties')->where('tenant_id', $tenantId)->count();
                $imoveis['ativos'] = $db->table('imo_properties')
                    ->where('tenant_id', $tenantId)
                    ->where('active', 1)
                    ->where('exibir_imovel', 1)
                    ->count();
            }
            $stats = [
                'leads' => [
                    'total' => $db->table('leads')->where('tenant_id', $tenantId)->count(),
                    'novos' => $db->table('leads')->where('tenant_id', $tenantId)->where('status', 'novo')->count(),
                    'em_atendimento' => $db->table('leads')->where('tenant_id', $tenantId)->where('status', 'em_atendimento')->count(),
                    'qualificados' => $db->table('leads')->where('tenant_id', $tenantId)->where('status', 'qualificado')->count(),
                    'fechados_mes' => $db->table('leads')
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'fechado')
                        ->whereRaw('MONTH(updated_at) = ?', [date('m')])
                        ->count()
                ],
                'conversas' => [
                    'ativas' => $db->table('conversas')->where('tenant_id', $tenantId)->where('status', 'ativa')->count(),
                    'hoje' => $db->table('conversas')->where('tenant_id', $tenantId)->whereDate('iniciada_em', date('Y-m-d'))->count(),
                    'aguardando' => $db->table('conversas')->where('tenant_id', $tenantId)->where('status', 'aguardando_corretor')->count()
                ],
                'corretores' => [
                    'total' => $db->table('users')->where('tenant_id', $tenantId)->where('role', 'admin')->where('is_active', true)->count(),
                    'online' => 0
                ],
                'imoveis' => $imoveis,
                'vistorias' => [
                    'total' => Schema::hasTable('vistorias') ? $db->table('vistorias')->where('tenant_id', $tenantId)->count() : 0,
                    'solicitacoes_pendentes' => Schema::hasTable('vistoria_solicitacoes')
                        ? $db->table('vistoria_solicitacoes')->where('tenant_id', $tenantId)->where('status', 'solicitada')->count()
                        : 0,
                    'em_andamento' => Schema::hasTable('vistoria_solicitacoes')
                        ? $db->table('vistoria_solicitacoes')->where('tenant_id', $tenantId)->where('status', 'andamento')->count()
                        : 0,
                ],
                'pessoas' => [
                    'total' => Schema::hasTable('pessoas') ? $db->table('pessoas')->where('tenant_id', $tenantId)->where('ativo', true)->count() : 0,
                    'fisicas' => Schema::hasTable('pessoas')
                        ? $db->table('pessoas')->where('tenant_id', $tenantId)->where('tipo', 'fisica')->where('ativo', true)->count()
                        : 0,
                    'juridicas' => Schema::hasTable('pessoas')
                        ? $db->table('pessoas')->where('tenant_id', $tenantId)->where('tipo', 'juridica')->where('ativo', true)->count()
                        : 0,
                ],
                'contestacoes' => [
                    'total' => Schema::hasTable('vistoria_contestacoes') ? $db->table('vistoria_contestacoes')->where('tenant_id', $tenantId)->count() : 0,
                    'apontadas' => Schema::hasTable('vistoria_contestacoes')
                        ? $db->table('vistoria_contestacoes')->where('tenant_id', $tenantId)->where('status', 'apontada')->count()
                        : 0,
                ],
                'assinaturas' => [
                    'total' => Schema::hasTable('documentos_assinatura') ? $db->table('documentos_assinatura')->where('tenant_id', $tenantId)->count() : 0,
                    'pendentes' => Schema::hasTable('documentos_assinatura')
                        ? $db->table('documentos_assinatura')->where('tenant_id', $tenantId)->where('status', 'pendente')->count()
                        : 0,
                    'assinados' => Schema::hasTable('documentos_assinatura')
                        ? $db->table('documentos_assinatura')->where('tenant_id', $tenantId)->where('status', 'assinado')->count()
                        : 0,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gráfico de atendimentos (últimos 7 dias)
     * GET /api/dashboard/chart/atendimentos
     */
    public function chartAtendimentos(Request $request)
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? ($request->attributes->get('tenant_id') ?? null);

        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 403);
        }

        $db = app('db');
        $dataInicio = date('Y-m-d', strtotime('-7 days'));
        $dados = $db->table('conversas')
            ->select($db->raw('DATE(iniciada_em) as data'), $db->raw('COUNT(*) as total'))
            ->where('tenant_id', $tenantId)
            ->where('iniciada_em', '>=', $dataInicio)
            ->groupBy('data')
            ->orderBy('data')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $dados
        ]);
    }

    /**
     * Atividades recentes
     * GET /api/dashboard/atividades
     */
    public function atividades(Request $request)
    {
        try {
            $user = $request->user();
            $tenantId = $user->tenant_id ?? ($request->attributes->get('tenant_id') ?? null);

            if (!$tenantId) {
                return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 403);
            }

            $db = app('db');
            $conversas = $db->table('conversas')
                ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                ->select(
                    'conversas.id as conversa_id',
                    'conversas.lead_id',
                    'conversas.telefone',
                    'conversas.iniciada_em',
                    'leads.nome as lead_nome'
                )
                ->where('conversas.tenant_id', $tenantId)
                ->orderBy('conversas.iniciada_em', 'desc')
                ->limit(10)
                ->get()
                ->map(function($conv) {
                    return [
                        'tipo' => 'nova_conversa',
                        'descricao' => 'Nova conversa iniciada com ' . ($conv->lead_nome ?? $conv->telefone),
                        'timestamp' => $conv->iniciada_em,
                        'data' => [
                            'conversa_id' => $conv->conversa_id,
                            'lead_id' => $conv->lead_id
                        ]
                    ];
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
     * Timeline real do sistema
     * GET /api/dashboard/timeline
     */
    public function timeline(Request $request)
    {
        try {
            $user = $request->user();
            $tenantId = $user->tenant_id ?? ($request->attributes->get('tenant_id') ?? null);
            if (!$tenantId) {
                return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 403);
            }
            $isAdmin = in_array($user->role, ['admin', 'super_admin']);
            $perPage = min((int) ($request->input('per_page') ?? 20), 50);
            $beforeCursor = $request->input('before');
            $categoryFilter = $request->input('category', 'all');

            $db = app('db');
            $items = collect();
            $fetchLimit = $perPage + 10;

            // 1. system_logs
            if (in_array($categoryFilter, ['all', 'sistema', 'lead', 'whatsapp']) && Schema::hasTable('system_logs')) {
                $query = $db->table('system_logs')
                    ->whereIn('level', ['info', 'warning'])
                    ->orderBy('created_at', 'desc')
                    ->limit($fetchLimit);

                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
                if (!$isAdmin) {
                    $query->where('user_id', $user->id);
                }
                if ($beforeCursor) {
                    $query->where('created_at', '<', $beforeCursor);
                }
                if ($categoryFilter === 'lead') {
                    $query->whereIn('category', ['lead', 'automation']);
                } elseif ($categoryFilter === 'whatsapp') {
                    $query->whereIn('category', ['whatsapp', 'twilio']);
                } elseif ($categoryFilter === 'sistema') {
                    $query->whereIn('category', ['auth', 'webhook', 'integration', 'ia']);
                }

                $sysLogs = $query->get()->map(function ($log) {
                    $context = json_decode($log->context ?? '{}', true) ?: [];
                    return [
                        'id' => 'syslog_' . $log->id,
                        'type' => $log->action ?? 'system_event',
                        'category' => $log->category ?? 'sistema',
                        'title' => $this->mapSystemLogTitle($log->category ?? '', $log->action ?? ''),
                        'description' => mb_substr($log->message ?? '', 0, 150),
                        'actor_id' => $log->user_id,
                        'timestamp' => $log->created_at,
                        'icon' => $this->mapCategoryIcon($log->category ?? ''),
                        'color' => $this->mapCategoryColor($log->category ?? ''),
                        'link' => $this->extractLinkFromContext($context),
                        'metadata' => $context,
                    ];
                });
                $items = $items->merge($sysLogs);
            }

            // 2. pessoa_interacoes
            if (in_array($categoryFilter, ['all', 'interacao']) && Schema::hasTable('pessoa_interacoes')) {
                $query = $db->table('pessoa_interacoes')
                    ->leftJoin('pessoas', 'pessoa_interacoes.pessoa_id', '=', 'pessoas.id')
                    ->select('pessoa_interacoes.*', 'pessoas.nome as pessoa_nome')
                    ->orderBy('pessoa_interacoes.data_interacao', 'desc')
                    ->limit($fetchLimit);

                if ($tenantId) {
                    $query->where('pessoa_interacoes.tenant_id', $tenantId);
                }
                if (!$isAdmin) {
                    $query->where('pessoa_interacoes.user_id', $user->id);
                }
                if ($beforeCursor) {
                    $query->where('pessoa_interacoes.data_interacao', '<', $beforeCursor);
                }

                $interacoes = $query->get()->map(function ($i) {
                    return [
                        'id' => 'interacao_' . $i->id,
                        'type' => 'interacao_' . ($i->tipo ?? 'outro'),
                        'category' => 'interacao',
                        'title' => $this->mapInteracaoTitle($i->tipo ?? 'outro'),
                        'description' => trim(($i->assunto ? $i->assunto . ': ' : '') . mb_substr($i->descricao ?? '', 0, 120) . ' - ' . ($i->pessoa_nome ?? 'Pessoa')),
                        'actor_id' => $i->user_id,
                        'timestamp' => $i->data_interacao,
                        'icon' => $this->mapInteracaoIcon($i->tipo ?? 'outro'),
                        'color' => $this->mapInteracaoColor($i->tipo ?? 'outro'),
                        'link' => '/pessoas/' . $i->pessoa_id,
                        'metadata' => json_decode($i->metadata ?? '{}', true) ?: [],
                    ];
                });
                $items = $items->merge($interacoes);
            }

            // 3. atividades (lead activities)
            if (in_array($categoryFilter, ['all', 'lead']) && Schema::hasTable('atividades')) {
                $query = $db->table('atividades')
                    ->leftJoin('leads', 'atividades.lead_id', '=', 'leads.id')
                    ->select('atividades.*', 'leads.nome as lead_nome', 'leads.corretor_id')
                    ->orderBy('atividades.created_at', 'desc')
                    ->limit($fetchLimit);

                if ($tenantId) {
                    $query->where('atividades.tenant_id', $tenantId);
                }
                if (!$isAdmin) {
                    $query->where('leads.corretor_id', $user->id);
                }
                if ($beforeCursor) {
                    $query->where('atividades.created_at', '<', $beforeCursor);
                }

                $atividades = $query->get()->map(function ($a) {
                    return [
                        'id' => 'ativ_' . $a->id,
                        'type' => 'atividade_' . ($a->tipo ?? 'geral'),
                        'category' => 'lead',
                        'title' => 'Atividade de Lead',
                        'description' => mb_substr($a->descricao ?? '', 0, 150) . ($a->lead_nome ? ' - ' . $a->lead_nome : ''),
                        'actor_id' => $a->corretor_id ?? null,
                        'timestamp' => $a->created_at,
                        'icon' => 'activity',
                        'color' => 'purple',
                        'link' => $a->lead_id ? '/leads/' . $a->lead_id : null,
                        'metadata' => [],
                    ];
                });
                $items = $items->merge($atividades);
            }

            // 4. conversas
            if (in_array($categoryFilter, ['all', 'conversa'])) {
                $query = $db->table('conversas')
                    ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                    ->select('conversas.*', 'leads.nome as lead_nome')
                    ->orderBy('conversas.iniciada_em', 'desc')
                    ->limit($fetchLimit);

                if ($tenantId) {
                    $query->where('conversas.tenant_id', $tenantId);
                }
                if (!$isAdmin) {
                    $query->where('conversas.corretor_id', $user->id);
                }
                if ($beforeCursor) {
                    $query->where('conversas.iniciada_em', '<', $beforeCursor);
                }

                $conversas = $query->get()->map(function ($c) {
                    return [
                        'id' => 'conv_' . $c->id,
                        'type' => 'nova_conversa',
                        'category' => 'conversa',
                        'title' => 'Nova conversa',
                        'description' => 'Conversa iniciada com ' . ($c->lead_nome ?? $c->telefone ?? 'contato'),
                        'actor_id' => $c->corretor_id ?? null,
                        'timestamp' => $c->iniciada_em,
                        'icon' => 'message-square',
                        'color' => 'green',
                        'link' => '/chat',
                        'metadata' => ['status' => $c->status ?? null],
                    ];
                });
                $items = $items->merge($conversas);
            }

            // 5. leads criados
            if (in_array($categoryFilter, ['all', 'lead'])) {
                $query = $db->table('leads')
                    ->orderBy('created_at', 'desc')
                    ->limit($fetchLimit);

                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
                if (!$isAdmin) {
                    $query->where('corretor_id', $user->id);
                }
                if ($beforeCursor) {
                    $query->where('created_at', '<', $beforeCursor);
                }

                $leads = $query->get()->map(function ($l) {
                    return [
                        'id' => 'lead_' . $l->id,
                        'type' => 'lead_created',
                        'category' => 'lead',
                        'title' => 'Novo lead',
                        'description' => 'Lead "' . ($l->nome ?? 'Sem nome') . '" - Status: ' . ($l->status ?? 'novo'),
                        'actor_id' => $l->corretor_id ?? null,
                        'timestamp' => $l->created_at,
                        'icon' => 'user-plus',
                        'color' => 'blue',
                        'link' => '/leads',
                        'metadata' => ['status' => $l->status ?? null],
                    ];
                });
                $items = $items->merge($leads);
            }

            // 6. vistoria_solicitacoes
            if (in_array($categoryFilter, ['all', 'vistoria']) && Schema::hasTable('vistoria_solicitacoes')) {
                $query = $db->table('vistoria_solicitacoes')
                    ->whereNull('deleted_at')
                    ->orderBy('created_at', 'desc')
                    ->limit($fetchLimit);

                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
                if ($beforeCursor) {
                    $query->where('created_at', '<', $beforeCursor);
                }

                $vistorias = $query->get()->map(function ($v) {
                    return [
                        'id' => 'vistoria_' . $v->id,
                        'type' => 'vistoria_' . ($v->status ?? 'solicitada'),
                        'category' => 'vistoria',
                        'title' => 'Solicitacao de Vistoria',
                        'description' => 'Vistoria ' . ($v->codigo ?? '#' . $v->id) . ' - ' . ($v->cliente_nome ?? 'Cliente') . ' (' . ($v->status ?? 'solicitada') . ')',
                        'actor_id' => null,
                        'timestamp' => $v->created_at,
                        'icon' => 'clipboard-check',
                        'color' => 'orange',
                        'link' => '/vistorias/solicitacoes',
                        'metadata' => ['status' => $v->status ?? null],
                    ];
                });
                $items = $items->merge($vistorias);
            }

            // 7. documentos_assinatura
            if (in_array($categoryFilter, ['all', 'assinatura']) && Schema::hasTable('documentos_assinatura')) {
                $query = $db->table('documentos_assinatura')
                    ->whereNull('deleted_at')
                    ->orderBy('created_at', 'desc')
                    ->limit($fetchLimit);

                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
                if ($beforeCursor) {
                    $query->where('created_at', '<', $beforeCursor);
                }

                $assinaturas = $query->get()->map(function ($d) {
                    return [
                        'id' => 'assinatura_' . $d->id,
                        'type' => 'assinatura_' . ($d->status ?? 'pendente'),
                        'category' => 'assinatura',
                        'title' => 'Documento de Assinatura',
                        'description' => ($d->titulo ?? 'Documento') . ' - Status: ' . ($d->status ?? 'pendente'),
                        'actor_id' => null,
                        'timestamp' => $d->created_at,
                        'icon' => 'file-signature',
                        'color' => 'indigo',
                        'link' => '/assinaturas',
                        'metadata' => ['status' => $d->status ?? null],
                    ];
                });
                $items = $items->merge($assinaturas);
            }

            // Sort all by timestamp desc
            $sorted = $items->sortByDesc('timestamp')->values();

            // Resolve actor names in batch
            $userIds = $sorted->pluck('actor_id')->filter()->unique()->values();
            $userNames = [];
            if ($userIds->isNotEmpty()) {
                $userNames = $db->table('users')
                    ->whereIn('id', $userIds->toArray())
                    ->pluck('name', 'id')
                    ->toArray();
            }

            $sorted = $sorted->map(function ($item) use ($userNames) {
                $actorId = $item['actor_id'];
                $item['actor'] = [
                    'id' => $actorId,
                    'name' => ($actorId && isset($userNames[$actorId])) ? $userNames[$actorId] : 'Sistema',
                ];
                unset($item['actor_id']);
                return $item;
            });

            // Paginate
            $paginated = $sorted->take($perPage);
            $hasMore = $sorted->count() > $perPage;
            $nextCursor = $hasMore && $paginated->isNotEmpty() ? $paginated->last()['timestamp'] : null;

            return response()->json([
                'success' => true,
                'data' => $paginated->values(),
                'meta' => [
                    'per_page' => $perPage,
                    'has_more' => $hasMore,
                    'next_cursor' => $nextCursor,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function mapSystemLogTitle(string $category, string $action): string
    {
        $map = [
            'lead' => 'Evento de Lead',
            'whatsapp' => 'Mensagem WhatsApp',
            'ia' => 'Processamento IA',
            'twilio' => 'Evento Twilio',
            'webhook' => 'Webhook recebido',
            'auth' => 'Autenticacao',
            'automation' => 'Automacao',
            'integration' => 'Integracao',
        ];
        return $map[$category] ?? 'Evento do sistema';
    }

    private function mapCategoryIcon(string $category): string
    {
        $map = [
            'lead' => 'user-plus',
            'whatsapp' => 'message-circle',
            'ia' => 'brain',
            'twilio' => 'phone',
            'webhook' => 'globe',
            'auth' => 'shield',
            'automation' => 'zap',
            'integration' => 'plug',
        ];
        return $map[$category] ?? 'activity';
    }

    private function mapCategoryColor(string $category): string
    {
        $map = [
            'lead' => 'blue',
            'whatsapp' => 'green',
            'ia' => 'violet',
            'twilio' => 'cyan',
            'webhook' => 'gray',
            'auth' => 'red',
            'automation' => 'yellow',
            'integration' => 'teal',
        ];
        return $map[$category] ?? 'gray';
    }

    private function mapInteracaoTitle(string $tipo): string
    {
        $map = [
            'atendimento' => 'Atendimento realizado',
            'visita' => 'Visita ao imovel',
            'ligacao' => 'Ligacao telefonica',
            'email' => 'Email enviado',
            'whatsapp' => 'Mensagem WhatsApp',
            'reuniao' => 'Reuniao realizada',
            'proposta' => 'Proposta enviada',
            'contrato' => 'Contrato elaborado',
            'outro' => 'Outra interacao',
        ];
        return $map[$tipo] ?? 'Interacao';
    }

    private function mapInteracaoIcon(string $tipo): string
    {
        $map = [
            'atendimento' => 'headphones',
            'visita' => 'map-pin',
            'ligacao' => 'phone',
            'email' => 'mail',
            'whatsapp' => 'message-circle',
            'reuniao' => 'calendar',
            'proposta' => 'file-text',
            'contrato' => 'file-signature',
            'outro' => 'circle-dot',
        ];
        return $map[$tipo] ?? 'circle';
    }

    private function mapInteracaoColor(string $tipo): string
    {
        $map = [
            'atendimento' => 'blue',
            'visita' => 'emerald',
            'ligacao' => 'cyan',
            'email' => 'sky',
            'whatsapp' => 'green',
            'reuniao' => 'purple',
            'proposta' => 'amber',
            'contrato' => 'indigo',
            'outro' => 'gray',
        ];
        return $map[$tipo] ?? 'gray';
    }

    /**
     * Gráfico de atendimentos por corretor
     * GET /api/dashboard/chart/atendimentos-por-corretor
     */
    public function chartAtendimentosPorCorretor(Request $request)
    {
        try {
            $user = $request->user();
            $tenantId = $user->tenant_id ?? ($request->attributes->get('tenant_id') ?? null);

            if (!$tenantId) {
                return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 403);
            }

            $db = app('db');
            $dados = $db->table('leads')
                ->leftJoin('users', 'leads.corretor_id', '=', 'users.id')
                ->select(
                    'leads.corretor_id',
                    $db->raw('COALESCE(users.name, "Sem corretor") as corretor_nome'),
                    $db->raw('COUNT(*) as total')
                )
                ->where('leads.tenant_id', $tenantId)
                ->whereNotNull('leads.corretor_id')
                ->groupBy('leads.corretor_id', 'users.name')
                ->orderByDesc('total')
                ->limit(20)
                ->get();

            return response()->json(['success' => true, 'data' => $dados]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Gráfico de captações por corretor
     * GET /api/dashboard/chart/captacoes-por-corretor
     */
    public function chartCaptacoesPorCorretor(Request $request)
    {
        try {
            $user = $request->user();
            $tenantId = $user->tenant_id ?? ($request->attributes->get('tenant_id') ?? null);

            if (!$tenantId) {
                return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 403);
            }

            if (!Schema::hasTable('imo_properties')) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $db = app('db');
            $dados = $db->table('imo_properties')
                ->select(
                    $db->raw('COALESCE(NULLIF(captador_nome, ""), "Sem captador") as captador_nome'),
                    $db->raw('COUNT(*) as total')
                )
                ->where('tenant_id', $tenantId)
                ->groupBy('captador_nome')
                ->orderByDesc('total')
                ->limit(20)
                ->get();

            return response()->json(['success' => true, 'data' => $dados]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Gráfico de acessos ao portal por dia (últimos 30 dias)
     * GET /api/dashboard/chart/acessos-portal-por-dia
     */
    public function chartAcessosPortalPorDia(Request $request)
    {
        try {
            $user = $request->user();
            $tenantId = $user->tenant_id ?? ($request->attributes->get('tenant_id') ?? null);

            if (!$tenantId) {
                return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 403);
            }

            if (!Schema::hasTable('analytics_events')) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $db = app('db');
            $dataInicio = date('Y-m-d', strtotime('-29 days'));
            $dados = $db->table('analytics_events')
                ->select(
                    $db->raw('DATE(occurred_at) as data'),
                    $db->raw('COUNT(*) as total')
                )
                ->where('tenant_id', $tenantId)
                ->where('event_name', 'pageview')
                ->where('occurred_at', '>=', $dataInicio)
                ->groupBy('data')
                ->orderBy('data')
                ->get();

            return response()->json(['success' => true, 'data' => $dados]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function extractLinkFromContext(array $context): ?string
    {
        if (isset($context['lead_id'])) return '/leads';
        if (isset($context['conversa_id'])) return '/chat';
        if (isset($context['pessoa_id'])) return '/pessoas/' . $context['pessoa_id'];
        return null;
    }
}
