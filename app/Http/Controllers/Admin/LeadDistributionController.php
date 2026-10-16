<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Services\ConversationAssignmentNotificationService;
use App\Services\LeadDistributorAlertService;
use App\Services\LeadHandoffNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Central de Distribuição de leads: fila de leads sem corretor, distribuição individual, em lote e
 * automática, redistribuição e devolução à fila. Acesso restrito a administradores do tenant
 * (hoje: Alexsandra e Roberto).
 */
class LeadDistributionController extends Controller
{
    /** Status em que o lead já saiu do funil e não precisa de distribuição. */
    private const FINAL_STATUSES = ['fechado', 'perdido', 'descartado', 'arquivado'];

    /** Status que contam como carga de trabalho de um corretor. */
    private const ACTIVE_STATUSES = ['novo', 'em_atendimento', 'qualificado', 'proposta'];

    private const ASSIGNABLE_ROLES = ['corretor', 'agent', 'admin', 'super_admin'];

    private const ACTIVITY_TYPES = ['distribuicao', 'redistribuicao', 'devolucao'];

    public function __construct(
        private ConversationAssignmentNotificationService $notifications,
        private LeadHandoffNotificationService $handoff
    ) {
    }

    // ------------------------------------------------------------------ listagem

    /**
     * GET /api/admin/distribuicao
     * aba=pendentes|distribuidos|todos, periodo=dias (0 = tudo), q, origem, corretor_id, ordem, page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        [$user, $tenantId, $denied] = $this->authorizeAdmin($request);
        if ($denied) {
            return $denied;
        }

        $aba = in_array($request->query('aba'), ['pendentes', 'distribuidos', 'todos'], true)
            ? $request->query('aba')
            : 'pendentes';
        $perPage = max(5, min(100, (int) $request->query('per_page', 30)));

        $filtered = $this->filteredQuery($request, $tenantId);

        $counts = [
            'pendentes' => $this->pendingScope(clone $filtered)->count(),
            'distribuidos' => (clone $filtered)->whereNotNull('corretor_id')->count(),
            'todos' => (clone $filtered)->count(),
        ];

        $query = clone $filtered;
        if ($aba === 'pendentes') {
            $this->pendingScope($query);
        } elseif ($aba === 'distribuidos') {
            $query->whereNotNull('corretor_id');
        }

        if ($request->filled('corretor_id')) {
            $query->where('corretor_id', (int) $request->query('corretor_id'));
        }

        // Fila: o mais antigo primeiro (está esperando há mais tempo). Demais abas: o mais recente.
        $oldestFirst = $request->query('ordem', $aba === 'pendentes' ? 'antigos' : 'recentes') === 'antigos';
        $query->orderBy('created_at', $oldestFirst ? 'asc' : 'desc')->orderBy('id', $oldestFirst ? 'asc' : 'desc');

        $page = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $this->formatLeads($page->getCollection(), $tenantId),
            'counts' => $counts,
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/admin/distribuicao/resumo — indicadores e carga da equipe.
     */
    public function resumo(Request $request): JsonResponse
    {
        [$user, $tenantId, $denied] = $this->authorizeAdmin($request);
        if ($denied) {
            return $denied;
        }

        $base = $this->filteredQuery($request, $tenantId, ignoreFilters: true);
        $pending = $this->pendingScope(clone $base);

        $oldest = (clone $pending)->min('created_at');

        $byOrigin = ['chaves' => 0, 'mascote' => 0, 'portal' => 0, 'outros' => 0];
        foreach ((clone $pending)->get(['id', 'observacoes']) as $row) {
            $byOrigin[$this->originOf($row)['key']]++;
        }

        $todayStart = now()->startOfDay();
        $distributedToday = DB::table('atividades')
            ->where('tenant_id', $tenantId)
            ->whereIn('tipo', ['distribuicao', 'redistribuicao'])
            ->where('created_at', '>=', $todayStart)
            ->count();

        $avgMinutes = DB::table('atividades')
            ->join('leads', 'leads.id', '=', 'atividades.lead_id')
            ->where('atividades.tenant_id', $tenantId)
            ->where('atividades.tipo', 'distribuicao')
            ->where('atividades.created_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, leads.created_at, atividades.created_at)) as m')
            ->value('m');

        return response()->json([
            'success' => true,
            'data' => [
                'pendentes' => (clone $pending)->count(),
                'mais_antigo_min' => $oldest ? (int) Carbon::parse($oldest)->diffInMinutes(now()) : null,
                'pendentes_por_origem' => $byOrigin,
                'distribuidos_hoje' => $distributedToday,
                'tempo_medio_min' => $avgMinutes !== null ? (int) round((float) $avgMinutes) : null,
                'corretores' => $this->teamLoad($tenantId),
            ],
        ]);
    }

    /**
     * GET /api/admin/distribuicao/lead/{id} — detalhe com histórico de distribuição.
     */
    public function show(Request $request, $id): JsonResponse
    {
        [$user, $tenantId, $denied] = $this->authorizeAdmin($request);
        if ($denied) {
            return $denied;
        }

        $lead = Lead::withoutGlobalScopes()->where('tenant_id', $tenantId)->find((int) $id);
        if (!$lead) {
            return response()->json(['success' => false, 'message' => 'Lead não encontrado'], 404);
        }

        $history = DB::table('atividades')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $lead->id)
            ->whereIn('tipo', self::ACTIVITY_TYPES)
            ->orderByDesc('id')
            ->limit(30)
            ->get(['tipo', 'descricao', 'created_at'])
            ->map(fn ($a) => [
                'tipo' => $a->tipo,
                'descricao' => $a->descricao,
                'em' => Carbon::parse($a->created_at)->toIso8601String(),
            ])
            ->all();

        $item = $this->formatLeads(collect([$lead]), $tenantId)[0];
        $item['historico'] = $history;
        $item['email'] = $lead->email;
        $item['budget_min'] = $lead->budget_min;
        $item['localizacao'] = $lead->localizacao;
        $item['quartos'] = $lead->quartos;
        $item['observacoes'] = $this->plainText((string) $lead->observacoes, 1200);

        return response()->json(['success' => true, 'data' => $item]);
    }

    // ------------------------------------------------------------------ ações

    /**
     * POST /api/admin/distribuicao/atribuir
     * lead_ids[], corretor_id (null = devolve à fila), observacao?, notificar?
     */
    public function atribuir(Request $request): JsonResponse
    {
        [$user, $tenantId, $denied] = $this->authorizeAdmin($request);
        if ($denied) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'lead_ids' => 'required|array|min:1|max:200',
            'lead_ids.*' => 'integer',
            'corretor_id' => 'nullable|integer',
            'observacao' => 'nullable|string|max:300',
            'notificar' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
        }

        $corretorId = $request->input('corretor_id');
        $target = null;
        if ($corretorId !== null) {
            $target = $this->assignableUser($tenantId, (int) $corretorId);
            if (!$target) {
                return response()->json(['success' => false, 'message' => 'Corretor inválido ou inativo neste tenant'], 422);
            }
        }

        $result = $this->applyAssignment(
            $tenantId,
            $user,
            array_map('intval', $request->input('lead_ids')),
            $target,
            $request->input('observacao'),
            (bool) $request->input('notificar', true)
        );

        return response()->json(['success' => true] + $result);
    }

    /**
     * POST /api/admin/distribuicao/distribuir-automatico
     * Reparte leads pendentes entre os corretores ativos, sempre dando o próximo ao menos carregado.
     * lead_ids[]? (padrão: toda a fila do período), corretor_ids[]? (padrão: todos os corretores), preview?
     */
    public function distribuirAutomatico(Request $request): JsonResponse
    {
        [$user, $tenantId, $denied] = $this->authorizeAdmin($request);
        if ($denied) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'lead_ids' => 'sometimes|array|max:200',
            'lead_ids.*' => 'integer',
            'corretor_ids' => 'sometimes|array',
            'corretor_ids.*' => 'integer',
            'preview' => 'sometimes|boolean',
            'notificar' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
        }

        $brokers = $this->teamLoad($tenantId, brokersOnly: true);
        if ($request->filled('corretor_ids')) {
            $allowed = array_map('intval', $request->input('corretor_ids'));
            $brokers = array_values(array_filter($brokers, fn ($b) => in_array($b['id'], $allowed, true)));
        }

        if (empty($brokers)) {
            return response()->json(['success' => false, 'message' => 'Nenhum corretor ativo disponível para receber leads'], 422);
        }

        $query = $this->pendingScope($this->filteredQuery($request, $tenantId));
        if ($request->filled('lead_ids')) {
            $query->whereIn('id', array_map('intval', $request->input('lead_ids')));
        }

        $leads = $query->orderBy('created_at')->orderBy('id')->limit(200)->get(['id', 'nome']);
        if ($leads->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Não há leads aguardando distribuição'], 422);
        }

        $load = [];
        foreach ($brokers as $b) {
            $load[$b['id']] = (int) $b['carga'];
        }

        $plan = [];
        foreach ($leads as $lead) {
            // Menor carga primeiro; empate: quem recebeu menos nesta rodada, depois o menor id (estável).
            uasort($load, fn ($a, $b) => $a <=> $b);
            $brokerId = array_key_first($load);
            $plan[$brokerId][] = $lead->id;
            $load[$brokerId]++;
        }

        $summary = [];
        foreach ($brokers as $b) {
            $ids = $plan[$b['id']] ?? [];
            $summary[] = [
                'corretor_id' => $b['id'],
                'nome' => $b['nome'],
                'carga_atual' => $b['carga'],
                'receberia' => count($ids),
                'carga_final' => $b['carga'] + count($ids),
            ];
        }

        if ($request->boolean('preview')) {
            return response()->json(['success' => true, 'preview' => true, 'total' => $leads->count(), 'plano' => $summary]);
        }

        $applied = 0;
        $errors = 0;
        foreach ($plan as $brokerId => $ids) {
            $target = $this->assignableUser($tenantId, (int) $brokerId);
            if (!$target) {
                continue;
            }

            $res = $this->applyAssignment($tenantId, $user, $ids, $target, 'Distribuição automática', (bool) $request->input('notificar', true));
            $applied += $res['resumo']['distribuidos'] + $res['resumo']['redistribuidos'];
            $errors += $res['resumo']['erros'];
        }

        return response()->json([
            'success' => true,
            'preview' => false,
            'total' => $leads->count(),
            'distribuidos' => $applied,
            'erros' => $errors,
            'plano' => $summary,
        ]);
    }

    /**
     * POST /api/admin/distribuicao/reprocessar — reenvia os avisos de lead que ainda não chegaram à
     * distribuidora. Acionado pelo botão da Central (e, automaticamente, no login dela).
     */
    public function reprocessar(Request $request): JsonResponse
    {
        [$user, $tenantId, $denied] = $this->authorizeAdmin($request);
        if ($denied) {
            return $denied;
        }

        $result = app(LeadDistributorAlertService::class)->reprocessPendingForTenant($tenantId, 5);

        if ($result['ignorado']) {
            $message = 'Já estou reprocessando. Aguarde alguns segundos.';
        } elseif ($result['pendentes'] === 0) {
            $message = 'Nenhum aviso pendente: todos os leads já foram avisados.';
        } elseif ($result['falhas'] === 0) {
            $message = "{$result['alertados']} aviso(s) reenviado(s) com sucesso.";
        } else {
            $message = "{$result['alertados']} aviso(s) reenviado(s); {$result['falhas']} não puderam ser entregues (veja o número/e-mail da distribuidora).";
        }

        return response()->json(['success' => true, 'mensagem' => $message] + $result);
    }

    // ------------------------------------------------------------------ núcleo

    /**
     * Aplica a atribuição em DB direto (sem observers: o LeadObserver reenviaria o lead ao Chaves na Mão
     * e recalcularia pessoas a cada atualização). Mantém conversas e pessoa em sincronia e registra histórico.
     *
     * @return array{resumo: array, resultados: array, mensagem: string}
     */
    private function applyAssignment(int $tenantId, User $by, array $leadIds, ?User $target, ?string $note, bool $notify): array
    {
        $leads = Lead::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_values(array_unique($leadIds)))
            ->get();

        $brokerNames = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $leads->pluck('corretor_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        $results = [];
        $assignedToTarget = collect();
        $counts = ['distribuidos' => 0, 'redistribuidos' => 0, 'devolvidos' => 0, 'sem_alteracao' => 0, 'erros' => 0];

        foreach (array_diff($leadIds, $leads->pluck('id')->all()) as $missing) {
            $results[] = ['lead_id' => (int) $missing, 'ok' => false, 'acao' => 'nao_encontrado'];
            $counts['erros']++;
        }

        foreach ($leads as $lead) {
            $previousId = $lead->corretor_id ? (int) $lead->corretor_id : null;
            $targetId = $target?->id;

            if ($previousId === $targetId) {
                $results[] = ['lead_id' => $lead->id, 'ok' => true, 'acao' => 'sem_alteracao'];
                $counts['sem_alteracao']++;
                continue;
            }

            try {
                DB::transaction(function () use ($lead, $previousId, $targetId, $tenantId, $by, $target, $note, $brokerNames, &$results, &$counts, &$assignedToTarget) {
                    $now = now();
                    $status = (string) $lead->status;

                    if ($targetId) {
                        $newStatus = in_array($status, ['', 'novo', 'qualificado'], true) ? 'em_atendimento' : $status;
                    } else {
                        $newStatus = $status === 'em_atendimento' ? 'novo' : $status;
                    }

                    DB::table('leads')->where('id', $lead->id)->where('tenant_id', $tenantId)->update([
                        'corretor_id' => $targetId,
                        'status' => $newStatus ?: null,
                        // Quem distribui já viu o lead: encerra o reenvio do alerta de lead novo.
                        'distribuidor_notificado_em' => $lead->distribuidor_notificado_em ?? $now,
                        'updated_at' => $now,
                    ]);

                    // Conversas já existentes seguem o lead (o corretor as vê no Chat); não cria conversas novas.
                    DB::table('conversas')
                        ->where('tenant_id', $tenantId)
                        ->where('lead_id', $lead->id)
                        ->where('status', '!=', 'encerrada')
                        ->update([
                            'corretor_id' => $targetId,
                            'status' => $targetId ? 'ativa' : 'aguardando_corretor',
                            'updated_at' => $now,
                        ]);

                    if ($lead->pessoa_id) {
                        DB::table('pessoas')->where('id', $lead->pessoa_id)->update([
                            'corretor_responsavel_id' => $targetId,
                            'updated_at' => $now,
                        ]);
                    }

                    if ($targetId && !$previousId) {
                        $tipo = 'distribuicao';
                        $descricao = "Distribuído para {$target->name} por {$by->name}";
                    } elseif ($targetId) {
                        $tipo = 'redistribuicao';
                        $descricao = "Redistribuído de " . ($brokerNames[$previousId] ?? "#{$previousId}") . " para {$target->name} por {$by->name}";
                    } else {
                        $tipo = 'devolucao';
                        $descricao = "Devolvido à fila por {$by->name} (estava com " . ($brokerNames[$previousId] ?? "#{$previousId}") . ')';
                    }

                    if ($note) {
                        $descricao .= " — {$note}";
                    }

                    DB::table('atividades')->insert([
                        'tenant_id' => $tenantId,
                        'lead_id' => $lead->id,
                        'tipo' => $tipo,
                        'descricao' => mb_substr($descricao, 0, 500),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $counts[match ($tipo) {
                        'distribuicao' => 'distribuidos',
                        'redistribuicao' => 'redistribuidos',
                        default => 'devolvidos',
                    }]++;

                    $results[] = ['lead_id' => $lead->id, 'ok' => true, 'acao' => $tipo];
                });

                if ($target) {
                    $assignedToTarget->push($lead->fresh());
                }
            } catch (\Throwable $e) {
                Log::error('[LeadDistribution] Falha ao atribuir lead', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
                $results[] = ['lead_id' => $lead->id, 'ok' => false, 'acao' => 'erro'];
                $counts['erros']++;
            }
        }

        $this->markDistributorNotificationsRead($tenantId, $leads->pluck('id')->all());

        if ($notify && $target && $assignedToTarget->isNotEmpty() && (int) $target->id !== (int) $by->id) {
            try {
                $this->notifications->alertBrokerOfAssignments($target, $assignedToTarget, $by, $note);
            } catch (\Throwable $e) {
                Log::warning('[LeadDistribution] Falha ao avisar o corretor', ['corretor_id' => $target->id, 'error' => $e->getMessage()]);
            }
        }

        $parts = [];
        if ($counts['distribuidos']) {
            $parts[] = "{$counts['distribuidos']} distribuído(s)";
        }
        if ($counts['redistribuidos']) {
            $parts[] = "{$counts['redistribuidos']} redistribuído(s)";
        }
        if ($counts['devolvidos']) {
            $parts[] = "{$counts['devolvidos']} devolvido(s) à fila";
        }
        if ($counts['sem_alteracao']) {
            $parts[] = "{$counts['sem_alteracao']} já estavam com esse destino";
        }
        if ($counts['erros']) {
            $parts[] = "{$counts['erros']} com erro";
        }

        return [
            'resumo' => $counts,
            'resultados' => $results,
            'mensagem' => $parts ? implode(' · ', $parts) : 'Nada a fazer',
        ];
    }

    /** Os avisos "novo lead" da distribuidora deixam de contar como não lidos quando o lead é tratado. */
    private function markDistributorNotificationsRead(int $tenantId, array $leadIds): void
    {
        try {
            DB::table('notifications')
                ->where('tenant_id', $tenantId)
                ->where('is_read', false)
                ->whereIn('data->event', ['lead_created', 'lead_recontact'])
                ->whereIn('data->lead_id', $leadIds)
                ->update(['is_read' => true, 'read_at' => now()]);
        } catch (\Throwable $e) {
            Log::info('[LeadDistribution] Não foi possível marcar avisos como lidos', ['error' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------ consultas auxiliares

    /**
     * @return array{0: User|null, 1: int|null, 2: JsonResponse|null}
     */
    private function authorizeAdmin(Request $request): array
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'super_admin'], true)) {
            return [null, null, response()->json(['success' => false, 'message' => 'Acesso restrito a administradores'], 403)];
        }

        $tenantId = (int) ($request->attributes->get('tenant_id') ?: $user->tenant_id);
        if ($tenantId <= 0) {
            return [null, null, response()->json(['success' => false, 'message' => 'Tenant não identificado'], 403)];
        }

        return [$user, $tenantId, null];
    }

    private function filteredQuery(Request $request, int $tenantId, bool $ignoreFilters = false)
    {
        $query = Lead::withoutGlobalScopes()->where('tenant_id', $tenantId);

        $periodo = $request->query('periodo', 30);
        $days = $periodo === 'todos' ? 0 : (int) $periodo;
        if ($days > 0) {
            $query->where('created_at', '>=', now()->subDays($days));
        }

        if ($ignoreFilters) {
            return $query;
        }

        if ($request->filled('q')) {
            $term = '%' . trim((string) $request->query('q')) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('nome', 'like', $term)
                    ->orWhere('telefone', 'like', $term)
                    ->orWhere('whatsapp', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('observacoes', 'like', $term);
            });
        }

        match ($request->query('origem')) {
            'chaves' => $query->where('observacoes', 'like', '%Chaves na%'),
            'mascote' => $query->where('observacoes', 'like', '%mascote do portal%'),
            'portal' => $query->where('observacoes', 'like', '%[Chat Portal%')->where('observacoes', 'not like', '%mascote do portal%'),
            'outros' => $query->where(function ($q) {
                $q->whereNull('observacoes')->orWhere(function ($q2) {
                    $q2->where('observacoes', 'not like', '%Chaves na%')
                        ->where('observacoes', 'not like', '%mascote do portal%')
                        ->where('observacoes', 'not like', '%[Chat Portal%');
                });
            }),
            default => null,
        };

        return $query;
    }

    private function pendingScope($query)
    {
        return $query->whereNull('corretor_id')->where(function ($q) {
            $q->whereNull('status')->orWhereNotIn('status', self::FINAL_STATUSES);
        });
    }

    private function assignableUser(int $tenantId, int $userId): ?User
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->where('is_active', true)
            ->whereIn('role', self::ASSIGNABLE_ROLES)
            ->first();
    }

    /**
     * Equipe que pode receber leads, com a carga atual (leads ativos) e os recebidos hoje.
     */
    private function teamLoad(int $tenantId, bool $brokersOnly = false): array
    {
        $roles = $brokersOnly ? ['corretor', 'agent'] : self::ASSIGNABLE_ROLES;

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $load = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereIn('corretor_id', $users->pluck('id')->all())
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->selectRaw('corretor_id, COUNT(*) c')
            ->groupBy('corretor_id')
            ->pluck('c', 'corretor_id');

        $today = DB::table('atividades')
            ->where('tenant_id', $tenantId)
            ->whereIn('tipo', ['distribuicao', 'redistribuicao'])
            ->where('created_at', '>=', now()->startOfDay())
            ->where('descricao', 'like', '% para %')
            ->get(['descricao'])
            ->reduce(function ($carry, $row) use ($users) {
                foreach ($users as $u) {
                    if (str_contains($row->descricao, " para {$u->name} por ")) {
                        $carry[$u->id] = ($carry[$u->id] ?? 0) + 1;
                        break;
                    }
                }

                return $carry;
            }, []);

        return $users->map(fn ($u) => [
            'id' => (int) $u->id,
            'nome' => $u->name,
            'papel' => $u->role === 'corretor' || $u->role === 'agent' ? 'corretor' : 'admin',
            'carga' => (int) ($load[$u->id] ?? 0),
            'recebidos_hoje' => (int) ($today[$u->id] ?? 0),
        ])->values()->all();
    }

    // ------------------------------------------------------------------ apresentação

    private function formatLeads($leads, int $tenantId): array
    {
        if ($leads->isEmpty()) {
            return [];
        }

        $ids = $leads->pluck('id')->all();

        $brokers = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $leads->pluck('corretor_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        $lastActivity = DB::table('atividades')
            ->where('tenant_id', $tenantId)
            ->whereIn('lead_id', $ids)
            ->whereIn('tipo', self::ACTIVITY_TYPES)
            ->selectRaw('lead_id, MAX(created_at) as em')
            ->groupBy('lead_id')
            ->pluck('em', 'lead_id');

        $conversations = DB::table('conversas')
            ->where('tenant_id', $tenantId)
            ->whereIn('lead_id', $ids)
            ->selectRaw('lead_id, MAX(id) as id')
            ->groupBy('lead_id')
            ->pluck('id', 'lead_id');

        return $leads->map(function (Lead $lead) use ($brokers, $lastActivity, $conversations) {
            $origin = $this->originOf($lead);
            $createdAt = $lead->created_at ? Carbon::parse($lead->created_at) : null;
            $pending = empty($lead->corretor_id) && !in_array($lead->status, self::FINAL_STATUSES, true);

            $interest = $this->handoff->extractPropertyInterest($lead);
            $message = $this->handoff->extractOriginalMessage($lead);
            if ($message === '') {
                $message = $this->plainText((string) $lead->observacoes, 220);
            }

            return [
                'id' => (int) $lead->id,
                'nome' => $lead->nome ?: ($lead->whatsapp_name ?: 'Sem nome'),
                'telefone' => $lead->telefone && $lead->telefone !== '00000000000' ? $lead->telefone : ($lead->whatsapp ?: null),
                'email' => $lead->email,
                'status' => $lead->status,
                'classificacao' => $lead->classificacao,
                'origem' => $origin,
                'interesse' => $interest !== '' ? $interest : null,
                'mensagem' => $message !== '' ? $this->plainText($message, 220) : null,
                'orcamento_max' => $lead->budget_max,
                'criado_em' => $createdAt?->toIso8601String(),
                'espera_min' => $pending && $createdAt ? (int) $createdAt->diffInMinutes(now()) : null,
                'pendente' => $pending,
                'corretor' => $lead->corretor_id
                    ? ['id' => (int) $lead->corretor_id, 'nome' => $brokers[$lead->corretor_id] ?? "#{$lead->corretor_id}"]
                    : null,
                'distribuido_em' => isset($lastActivity[$lead->id]) ? Carbon::parse($lastActivity[$lead->id])->toIso8601String() : null,
                'conversa_id' => isset($conversations[$lead->id]) ? (int) $conversations[$lead->id] : null,
            ];
        })->values()->all();
    }

    /** @return array{key: string, label: string} */
    private function originOf($lead): array
    {
        $obs = (string) ($lead->observacoes ?? '');

        if (stripos($obs, 'Chaves na') !== false) {
            return ['key' => 'chaves', 'label' => 'Chaves na Mão'];
        }

        if (stripos($obs, 'mascote do portal') !== false) {
            return ['key' => 'mascote', 'label' => 'Mascote do portal'];
        }

        if (stripos($obs, '[Chat Portal') !== false) {
            return ['key' => 'portal', 'label' => 'Portal'];
        }

        return ['key' => 'outros', 'label' => 'Outros'];
    }

    private function plainText(string $text, int $limit): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1) . '…' : $text;
    }
}
