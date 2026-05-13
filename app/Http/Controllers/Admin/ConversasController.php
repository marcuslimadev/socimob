<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Conversa;
use App\Models\LeadDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConversationAssignmentNotificationService;
use App\Services\EvolutionApiService;
use App\Services\MetaCloudGateway;
use App\Services\OpenAIService;
use App\Services\TwilioService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
                    : $this->summarizeLeadObservationForQueue($conversa->lead_observacoes ?? null);

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

    private function summarizeLeadObservationForQueue(?string $observations): ?string
    {
        $text = $this->compactText((string) $observations, 220);
        if ($text === '') {
            return null;
        }

        if (preg_match('/Mensagem:\s*(.+?)(?:\s+Origem:|\s+Im[oó]vel:|\s+Refer[êe]ncia:|\s+An[uú]ncio:|$)/iu', $text, $matches)) {
            return trim($matches[1]);
        }

        return $text;
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

    public function dispararAtendimentos(Request $request)
    {
        $user = $request->user();
        if (!$this->isAdminRole($user->role ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas administradores podem disparar retomadas de atendimento',
            ], 403);
        }

        $tenantId = (int) $request->attributes->get('tenant_id');
        $limit = min(max((int) $request->input('limit', 500), 1), 1000);
        $targetDate = $this->parseDispatchDate($request->input('target_date'));

        $conversas = $this->eligibleDispatchConversationsQuery($tenantId, $targetDate)
            ->select(
                'conversas.*',
                'leads.nome as lead_nome',
                'leads.telefone as lead_telefone',
                'leads.whatsapp as lead_whatsapp',
                'leads.observacoes as lead_observacoes',
                'leads.observacoes_cliente as lead_observacoes_cliente',
                'leads.caracteristicas_desejadas as lead_caracteristicas',
                'leads.localizacao as lead_localizacao',
                'leads.preferencia_bairro as lead_preferencia_bairro',
                'leads.preferencia_tipo_imovel as lead_tipo',
                'leads.objetivo_compra as lead_objetivo',
                'leads.budget_max as lead_budget_max',
                'leads.quartos as lead_quartos'
            )
            ->orderByRaw('COALESCE(conversas.ultima_atividade, conversas.created_at) ASC')
            ->limit($limit)
            ->get();

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($conversas as $conversa) {
            $message = $this->buildAiReengagementMessage($conversa);
            $result = $this->sendAutomatedConversationMessage((int) $tenantId, $conversa, $message);

            if (($result['success'] ?? false) === true) {
                $sent++;
            } else {
                $failed++;
                $errors[] = [
                    'conversa_id' => (int) $conversa->id,
                    'error' => $result['error'] ?? 'Falha desconhecida',
                ];
            }
        }

        return response()->json([
            'success' => $failed === 0,
            'message' => $sent > 0
                ? "Retomada enviada para {$sent} atendimento(s)" . ($targetDate ? ' de ' . $targetDate->format('d/m/Y') : '') . "."
                : 'Nenhum atendimento elegível para retomada agora.',
            'data' => [
                'target_date' => $targetDate?->toDateString(),
                'eligible' => $conversas->count(),
                'sent' => $sent,
                'failed' => $failed,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 10),
            ],
        ], $failed > 0 && $sent === 0 ? 502 : 200);
    }

    public function diasElegiveisDisparo(Request $request)
    {
        $user = $request->user();
        if (!$this->isAdminRole($user->role ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas administradores podem ver o calendário de disparos',
            ], 403);
        }

        $tenantId = (int) $request->attributes->get('tenant_id');
        $rows = $this->eligibleDispatchConversationsQuery($tenantId)
            ->selectRaw('DATE(COALESCE(conversas.iniciada_em, conversas.created_at)) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderByDesc('date')
            ->limit(90)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function sugerirRepescagemConversa(Request $request, $id)
    {
        $user = $request->user();
        if (!$this->isAdminRole($user->role ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas administradores podem sugerir repescagem',
            ], 403);
        }

        $tenantId = (int) $request->attributes->get('tenant_id');
        $conversa = $this->findConversationForRepescagem($tenantId, $id);

        if (!$conversa) {
            return response()->json([
                'success' => false,
                'message' => 'Conversa não encontrada',
            ], 404);
        }

        $message = $this->buildContextualReengagementMessage($tenantId, $conversa);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $message,
            ],
        ]);
    }

    public function enviarRepescagemConversa(Request $request, $id)
    {
        $user = $request->user();
        if (!$this->isAdminRole($user->role ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas administradores podem enviar repescagem',
            ], 403);
        }

        $request->validate([
            'content' => 'required|string|min:10|max:1200',
        ]);

        $tenantId = (int) $request->attributes->get('tenant_id');
        $conversa = $this->findConversationForRepescagem($tenantId, $id);

        if (!$conversa) {
            return response()->json([
                'success' => false,
                'message' => 'Conversa não encontrada',
            ], 404);
        }

        $result = $this->sendAutomatedConversationMessage($tenantId, $conversa, trim((string) $request->input('content')));

        if (($result['success'] ?? false) !== true) {
            return response()->json([
                'success' => false,
                'message' => 'Falha ao enviar repescagem via WhatsApp',
                'error' => $result['error'] ?? null,
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Repescagem enviada para esta conversa',
        ]);
    }

    private function eligibleDispatchConversationsQuery(int $tenantId, ?Carbon $targetDate = null)
    {
        $todayStart = Carbon::now()->startOfDay();
        $todayEnd = Carbon::now()->endOfDay();

        $query = DB::table('conversas')
            ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
            ->where(function ($q) use ($tenantId) {
                $q->where('conversas.tenant_id', $tenantId)
                    ->orWhere('leads.tenant_id', $tenantId);
            })
            ->whereNull('conversas.corretor_id')
            ->whereNotNull('conversas.telefone')
            ->where('conversas.telefone', '<>', '')
            ->where(function ($q) {
                $q->whereNull('conversas.status')
                    ->orWhereNotIn('conversas.status', ['finalizada', 'fechada', 'cancelada']);
            })
            ->where(function ($q) {
                $q->whereNull('leads.status')
                    ->orWhereNotIn('leads.status', ['fechado', 'perdido']);
            })
            ->whereNotExists(function ($q) use ($todayStart, $todayEnd) {
                $q->select(DB::raw(1))
                    ->from('mensagens as retomadas')
                    ->whereColumn('retomadas.conversa_id', 'conversas.id')
                    ->where('retomadas.direction', 'outgoing')
                    ->where('retomadas.status', 'sent')
                    ->whereBetween('retomadas.created_at', [$todayStart, $todayEnd])
                    ->where('retomadas.content', 'like', '%Quero seguir te ajudando com opções compatíveis%');
            });

        if ($targetDate) {
            $query->whereDate(DB::raw('COALESCE(conversas.iniciada_em, conversas.created_at)'), $targetDate->toDateString());
        }

        return $query;
    }

    private function parseDispatchDate($value): Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            abort(response()->json([
                'success' => false,
                'message' => 'Selecione um dia para disparar os atendimentos.',
            ], 422));
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            abort(response()->json([
                'success' => false,
                'message' => 'Data de disparo inválida.',
            ], 422));
        }
    }

    private function findConversationForRepescagem(int $tenantId, $id)
    {
        return DB::table('conversas')
            ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
            ->select(
                'conversas.*',
                'leads.nome as lead_nome',
                'leads.telefone as lead_telefone',
                'leads.whatsapp as lead_whatsapp',
                'leads.observacoes as lead_observacoes',
                'leads.observacoes_cliente as lead_observacoes_cliente',
                'leads.caracteristicas_desejadas as lead_caracteristicas',
                'leads.localizacao as lead_localizacao',
                'leads.preferencia_bairro as lead_preferencia_bairro',
                'leads.preferencia_tipo_imovel as lead_tipo',
                'leads.objetivo_compra as lead_objetivo',
                'leads.budget_max as lead_budget_max',
                'leads.quartos as lead_quartos'
            )
            ->where('conversas.id', $id)
            ->where(function ($q) use ($tenantId) {
                $q->where('conversas.tenant_id', $tenantId)
                    ->orWhere('leads.tenant_id', $tenantId);
            })
            ->first();
    }

    private function buildContextualReengagementMessage(int $tenantId, $conversa): string
    {
        $history = $this->buildRepescagemConversationHistory((int) $conversa->id);
        $leadContext = $this->buildRepescagemLeadContext($conversa);
        $fallback = $this->buildAiReengagementMessage($conversa);

        try {
            $systemPrompt = "Você é Teresa, atendente da Exclusiva Lar Imóveis no WhatsApp.\n"
                . "Escreva UMA mensagem curta de repescagem para retomar uma conversa real.\n"
                . "Use somente informações confirmadas no histórico e nos dados do lead.\n"
                . "Seja cordial, humana e objetiva. Não invente imóveis disponíveis.\n"
                . "Reconheça o que o cliente pediu, especialmente orçamento, bairros, quantidade de quartos, pagamento à vista, armários, área privativa ou outras preferências.\n"
                . "Se a IA anterior falhou ou repetiu mensagem genérica, retome com cuidado sem criticar o atendimento.\n"
                . "Termine com uma pergunta simples que facilite a resposta do cliente.\n"
                . "Não use markdown, listas, links, aspas, emojis em excesso ou texto promocional.\n"
                . "Limite a 3 frases e até 650 caracteres.";

            $userPrompt = "Dados do lead:\n{$leadContext}\n\nHistórico recente da conversa:\n{$history}\n\nMensagem de repescagem:";
            $generated = trim(app(OpenAIService::class)->generateSimpleMessage($systemPrompt, $userPrompt));
            $generated = $this->sanitizeGeneratedRepescagemMessage($generated);

            if ($generated !== '') {
                return $generated;
            }
        } catch (\Throwable $e) {
            Log::warning('[ConversasController] Falha ao gerar repescagem contextual com IA', [
                'tenant_id' => $tenantId,
                'conversa_id' => $conversa->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return $fallback;
    }

    private function buildRepescagemConversationHistory(int $conversaId): string
    {
        $messages = DB::table('mensagens')
            ->where('conversa_id', $conversaId)
            ->whereNotNull('content')
            ->where('content', '<>', '')
            ->orderByDesc('created_at')
            ->limit(24)
            ->get()
            ->reverse()
            ->values();

        if ($messages->isEmpty()) {
            return 'Sem mensagens registradas.';
        }

        return $messages
            ->map(function ($message) {
                $sender = ($message->direction ?? null) === 'incoming'
                    ? 'Cliente'
                    : (empty($message->user_id ?? null) ? 'Teresa/IA' : 'Atendente');
                $content = $this->compactText((string) $message->content, 700);
                return "{$sender}: {$content}";
            })
            ->implode("\n");
    }

    private function buildRepescagemLeadContext($conversa): string
    {
        $parts = [];

        if (!empty($conversa->lead_nome)) {
            $parts[] = 'Nome: ' . $conversa->lead_nome;
        }

        $context = $this->buildConversationContextLine($conversa);
        if ($context !== '') {
            $parts[] = 'Origem/interesse: ' . $context;
        }

        $criteria = $this->buildConversationCriteriaLine($conversa);
        if ($criteria !== '') {
            $parts[] = 'Critérios registrados: ' . $criteria;
        }

        $observations = $this->compactText(strip_tags((string) ($conversa->lead_observacoes_cliente ?: $conversa->lead_observacoes ?: '')), 900);
        if ($observations !== '') {
            $parts[] = 'Observações: ' . $observations;
        }

        return $parts ? implode("\n", $parts) : 'Sem dados estruturados além do histórico.';
    }

    private function compactText(string $value, int $limit = 600): string
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 1)) . '...';
    }

    private function sanitizeGeneratedRepescagemMessage(string $message): string
    {
        $message = trim(strip_tags($message));
        $message = preg_replace('/^["\'`]+|["\'`]+$/u', '', $message) ?? $message;
        $message = preg_replace('/\s{3,}/u', "\n\n", $message) ?? $message;

        if ($message === '' || mb_strlen($message) < 20) {
            return '';
        }

        return $this->compactText($message, 900);
    }

    private function buildAiReengagementMessage($conversa): string
    {
        $name = trim((string) ($conversa->lead_nome ?? ''));
        $firstName = $name !== '' ? preg_split('/\s+/', $name)[0] : '';
        $greetingName = $firstName !== '' ? ", {$firstName}" : '';
        $context = $this->buildConversationContextLine($conversa);
        $criteria = $this->buildConversationCriteriaLine($conversa);

        $message = "Bom dia{$greetingName}! Tudo bem?\n";
        $message .= "Aqui é a Teresa, da Exclusiva Lar Imóveis. ";
        $message .= $context ?: 'Vi seu cadastro e quero retomar seu atendimento com cuidado.';
        if ($criteria) {
            $message .= "\nTenho anotado: {$criteria}.";
        }
        $message .= "\nQuero seguir te ajudando com opções compatíveis. Você ainda está buscando esse imóvel?";

        return $message;
    }

    private function buildConversationContextLine($conversa): string
    {
        $observations = trim(strip_tags((string) ($conversa->lead_observacoes_cliente ?: $conversa->lead_observacoes ?: '')));
        $observations = html_entity_decode($observations, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $observations = preg_replace('/\s+/u', ' ', $observations) ?? $observations;

        if (preg_match('/(?:Imóvel|Imovel):\s*([^|\.]{3,120})/iu', $observations, $matches)) {
            return 'Vi seu interesse no imóvel ' . trim($matches[1]) . ' e quero continuar de onde paramos.';
        }

        if (preg_match('/Refer[êe]ncia(?: do imóvel)?:\s*([A-Za-z0-9\-\/]+)/iu', $observations, $matches)) {
            return 'Vi seu interesse no imóvel de referência ' . trim($matches[1]) . ' e quero continuar de onde paramos.';
        }

        if (stripos($observations, 'Chaves') !== false) {
            return 'Vi que você chegou pelo Chaves na Mão e quero continuar de onde paramos.';
        }

        return '';
    }

    private function buildConversationCriteriaLine($conversa): string
    {
        $parts = [];

        if (!empty($conversa->lead_objetivo)) {
            $parts[] = mb_strtolower((string) $conversa->lead_objetivo);
        }

        $location = trim((string) ($conversa->lead_preferencia_bairro ?: $conversa->lead_localizacao ?: ''));
        if ($location !== '') {
            $parts[] = $location;
        }

        if (!empty($conversa->lead_tipo)) {
            $parts[] = $conversa->lead_tipo;
        }

        if (!empty($conversa->lead_budget_max)) {
            $parts[] = 'até R$ ' . number_format((float) $conversa->lead_budget_max, 0, ',', '.');
        }

        if (!empty($conversa->lead_quartos)) {
            $parts[] = ((int) $conversa->lead_quartos) . ' quarto(s)';
        }

        if (!empty($conversa->lead_caracteristicas)) {
            $parts[] = $conversa->lead_caracteristicas;
        }

        return implode(', ', array_filter($parts));
    }

    private function sendAutomatedConversationMessage(int $tenantId, $conversa, string $content): array
    {
        $payload = [
            'tenant_id' => $tenantId,
            'conversa_id' => $conversa->id,
            'direction' => 'outgoing',
            'message_type' => 'text',
            'content' => $content,
            'status' => 'queued',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        if (Schema::hasColumn('mensagens', 'sent_at')) {
            $payload['sent_at'] = Carbon::now();
        }

        $mensagemId = DB::table('mensagens')->insertGetId($payload);

        try {
            $driver = strtolower((string) config('whatsapp.driver', 'evolution'));
            if ($driver === 'meta_cloud') {
                $resultado = app(MetaCloudGateway::class)->sendMessage($conversa->telefone, $content, $tenantId);
            } else {
                $gateway = $driver === 'evolution'
                    ? app(EvolutionApiService::class)
                    : app(TwilioService::class);

                $resultado = $gateway->sendMessage($conversa->telefone, $content);
            }

            if (empty($resultado['success'])) {
                DB::table('mensagens')->where('id', $mensagemId)->update([
                    'status' => 'failed',
                    'updated_at' => Carbon::now(),
                ]);

                return [
                    'success' => false,
                    'error' => $resultado['error'] ?? 'Provider recusou o envio',
                ];
            }

            DB::table('mensagens')->where('id', $mensagemId)->update([
                'message_sid' => $resultado['message_sid'] ?? null,
                'status' => 'sent',
                'sent_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            DB::table('conversas')->where('id', $conversa->id)->update([
                'stage' => $conversa->stage ?: 'coleta_dados',
                'status' => 'ativa',
                'ultima_atividade' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            return ['success' => true];
        } catch (\Throwable $e) {
            DB::table('mensagens')->where('id', $mensagemId)->update([
                'status' => 'failed',
                'updated_at' => Carbon::now(),
            ]);

            Log::error('[ConversasController] Falha na retomada automatica', [
                'conversa_id' => $conversa->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
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
     * Enviar foto ou arquivo pelo chat e anexar automaticamente ao lead.
     */
    public function enviarMidia(Request $request, $id)
    {
        try {
            $request->validate([
                'arquivo' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv|max:51200',
                'content' => 'nullable|string|max:1024',
            ]);

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

            if ($this->isBrokerRole($user->role ?? null)) {
                if (!$this->brokerCanAccessConversation($user, $conversa)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Você só pode responder conversas atribuídas a você'
                    ], 403);
                }
            } elseif (!empty($user?->id)) {
                DB::table('conversas')
                    ->where('id', $id)
                    ->update([
                        'corretor_id' => $user->id,
                        'updated_at' => Carbon::now(),
                    ]);
                $conversa->corretor_id = $user->id;
            }

            $file = $request->file('arquivo');
            $caption = trim((string) $request->input('content', ''));
            $originalName = $file->getClientOriginalName() ?: 'arquivo';
            $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION));
            $mimeType = $file->getClientMimeType() ?: 'application/octet-stream';
            $messageType = $this->chatMessageTypeFromMime($mimeType, $extension);

            $leadFolder = $conversa->lead_id ? "lead-{$conversa->lead_id}" : "conversa-{$conversa->id}";
            $baseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'arquivo';
            $safeName = $baseName . '-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(6)) . ($extension ? ".{$extension}" : '');
            $path = $file->storeAs("chat/tenant-{$tenantId}/{$leadFolder}", $safeName, 'public');
            $storageUrl = Storage::disk('public')->url($path);
            $publicMediaUrl = $this->absoluteMediaUrl($request, $storageUrl);

            $payload = [
                'tenant_id' => $tenantId,
                'conversa_id' => $id,
                'direction' => 'outgoing',
                'message_type' => $messageType,
                'content' => $caption,
                'media_url' => $storageUrl,
                'status' => 'queued',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            if (Schema::hasColumn('mensagens', 'user_id')) {
                $payload['user_id'] = $user?->id;
            }

            $mensagemId = DB::table('mensagens')->insertGetId($payload);

            if (!empty($conversa->lead_id)) {
                LeadDocument::create([
                    'tenant_id' => $tenantId,
                    'lead_id' => $conversa->lead_id,
                    'conversa_id' => $conversa->id,
                    'mensagem_id' => $mensagemId,
                    'nome' => $originalName,
                    'tipo' => $messageType === 'image' ? 'foto_chat' : 'arquivo_chat',
                    'mime_type' => $mimeType,
                    'arquivo_url' => $storageUrl,
                    'status' => 'enviado_chat',
                ]);
            }

            try {
                $driver = strtolower((string) config('whatsapp.driver', 'evolution'));
                if ($driver === 'meta_cloud') {
                    $resultado = app(MetaCloudGateway::class)->sendMedia(
                        $conversa->telefone,
                        $caption !== '' ? $caption : null,
                        $publicMediaUrl,
                        (int) $tenantId
                    );
                } else {
                    $gateway = $driver === 'evolution'
                        ? app(EvolutionApiService::class)
                        : app(TwilioService::class);

                    $resultado = $gateway->sendMedia(
                        $conversa->telefone,
                        $caption,
                        $publicMediaUrl
                    );
                }

                if (empty($resultado['success'])) {
                    DB::table('mensagens')
                        ->where('id', $mensagemId)
                        ->update([
                            'status' => 'failed',
                            'updated_at' => Carbon::now(),
                        ]);

                    Log::error('Falha ao enviar mídia via provider WhatsApp', [
                        'mensagem_id' => $mensagemId,
                        'to' => $conversa->telefone,
                        'driver' => $driver,
                        'http_code' => $resultado['http_code'] ?? null,
                        'error' => $resultado['error'] ?? null,
                        'response' => $resultado['response'] ?? null,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Falha ao enviar arquivo via provider de WhatsApp',
                        'provider' => [
                            'driver' => $driver,
                            'http_code' => $resultado['http_code'] ?? null,
                            'error' => $resultado['error'] ?? null,
                        ]
                    ], 502);
                }

                DB::table('mensagens')
                    ->where('id', $mensagemId)
                    ->update([
                        'message_sid' => $resultado['message_sid'] ?? null,
                        'status' => 'sent',
                        'sent_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
            } catch (\Exception $e) {
                DB::table('mensagens')
                    ->where('id', $mensagemId)
                    ->update([
                        'status' => 'failed',
                        'updated_at' => Carbon::now(),
                    ]);

                Log::error('Erro ao enviar mídia via provider de WhatsApp', [
                    'mensagem_id' => $mensagemId,
                    'erro' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao enviar arquivo via provider de WhatsApp'
                ], 502);
            }

            DB::table('conversas')
                ->where('id', $id)
                ->update([
                    'ultima_atividade' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

            $mensagem = DB::table('mensagens')->find($mensagemId);

            return response()->json([
                'success' => true,
                'data' => $mensagem,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Arquivo inválido',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar mídia no chat', [
                'conversa_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar arquivo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function chatMessageTypeFromMime(?string $mimeType, ?string $extension = null): string
    {
        $mime = strtolower((string) $mimeType);
        $ext = strtolower((string) $extension);

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return 'image';
        }

        return 'document';
    }

    private function absoluteMediaUrl(Request $request, string $storageUrl): string
    {
        if (Str::startsWith($storageUrl, ['http://', 'https://'])) {
            return $storageUrl;
        }

        $requestBase = rtrim($request->getSchemeAndHttpHost(), '/');
        $configBase = rtrim((string) config('app.url'), '/');
        $base = ($requestBase !== '' && !str_contains($requestBase, 'localhost'))
            ? $requestBase
            : $configBase;

        if ($base === '') {
            $base = $requestBase;
        }

        if ($base !== '' && !str_contains($base, 'localhost') && str_starts_with($base, 'http://')) {
            $base = 'https://' . substr($base, 7);
        }

        return $base . '/' . ltrim($storageUrl, '/');
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
