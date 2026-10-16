<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ponto único para avisar a distribuidora (Alexsandra) de que há um lead novo para
 * distribuir. Todo caminho de criação de lead (observer, webhook do Chaves na Mão,
 * job, comando de reenvio) passa por aqui.
 *
 * O lead só é marcado como notificado (distribuidor_notificado_em) quando a Alexsandra
 * realmente recebeu o alerta. Os pendentes são reprocessados:
 *  - quando a distribuidora faz login (reprocessPendingForLogin) — o gatilho principal;
 *  - pelo comando leads:alert-distributor, se houver cron (até MAX_ATTEMPTS, dentro de
 *    MAX_AGE_HOURS desde a criação).
 */
class LeadDistributorAlertService
{
    public const MAX_ATTEMPTS = 10;
    public const MAX_AGE_HOURS = 24;

    /** Reprocessamento disparado pelo login da distribuidora: janela maior e sem limite de tentativas. */
    public const LOGIN_REPROCESS_DAYS = 7;
    public const LOGIN_REPROCESS_LIMIT = 25;
    public const LOGIN_REPROCESS_THROTTLE_SECONDS = 60;

    private const DISTRIBUTOR_NAME = 'Alexsandra';

    /** Modos de aviso quando um cliente já cadastrado entra em contato de novo. */
    public const RECONTACT_MODE_ALL = 'todos';         // sempre avisa (respeitando o intervalo mínimo)
    public const RECONTACT_MODE_FILTERED = 'filtrado'; // só sem corretor ou parado há X dias (padrão)
    public const RECONTACT_MODE_OFF = 'desligado';

    public const DEFAULT_RECONTACT_DAYS = 7;

    /** Intervalo mínimo entre avisos do mesmo lead (evita rajada de mensagens/formulários). */
    public const RECONTACT_COOLDOWN_MINUTES = 30;

    public function __construct(
        private ConversationAssignmentNotificationService $assignmentNotificationService,
        private LeadHandoffNotificationService $handoffNotificationService
    ) {
    }

    /**
     * Reprocessa os alertas pendentes quando a própria distribuidora faz login: reenvia os leads
     * que ainda não a alcançaram (falha de envio, sem cron, etc.). Ignora o limite de tentativas
     * e de idade do reenvio automático, pois o login é um novo gatilho. Só age para a usuária
     * que é a distribuidora do tenant e no máximo uma vez por minuto.
     *
     * @return int quantidade de leads alertados com sucesso
     */
    public function reprocessPendingForLogin(User $user): int
    {
        $tenantId = (int) $user->tenant_id;
        if ($tenantId <= 0) {
            return 0;
        }

        $distributor = $this->assignmentNotificationService->findDistributionUser($tenantId);
        if (!$distributor || (int) $distributor->id !== (int) $user->id) {
            return 0;
        }

        try {
            if (!Cache::add("lead-alert-reprocess:{$tenantId}", 1, self::LOGIN_REPROCESS_THROTTLE_SECONDS)) {
                return 0;
            }
        } catch (\Throwable $e) {
            // Cache sem suporte: segue sem limitar a frequência (o lock por lead evita duplicidade).
        }

        $pending = Lead::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('distribuidor_notificado_em')
            ->where('created_at', '>=', now()->subDays(self::LOGIN_REPROCESS_DAYS))
            ->orderBy('id')
            ->limit(self::LOGIN_REPROCESS_LIMIT)
            ->get();

        $delivered = 0;
        foreach ($pending as $lead) {
            if ($this->alert($lead, true)) {
                $delivered++;
            }
        }

        if ($pending->isNotEmpty()) {
            Log::info('[LeadDistributorAlert] Reprocessamento no login da distribuidora', [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'pendentes' => $pending->count(),
                'alertados' => $delivered,
            ]);
        }

        return $delivered;
    }

    /**
     * Alerta a distribuidora sobre o lead. Idempotente: retorna true sem reenviar se ela
     * já foi avisada; retorna false se não conseguiu (será retentado no próximo login da
     * distribuidora ou pelo comando agendado). Com $ignoreAge, ignora também o limite de
     * tentativas e de idade.
     */
    public function alert(Lead $lead, bool $ignoreAge = false): bool
    {
        if ($lead->distribuidor_notificado_em) {
            return true;
        }

        $lock = $this->acquireLock($lead->id);
        if ($lock === false) {
            // Outro processo está alertando este lead agora.
            return false;
        }

        try {
            // Relê dentro do lock: outro processo pode ter concluído o alerta entretanto.
            $fresh = Lead::withoutGlobalScopes()->find($lead->id);
            if (!$fresh) {
                return false;
            }

            if ($fresh->distribuidor_notificado_em) {
                return true;
            }

            if (!$this->isEligible($fresh, $ignoreAge)) {
                return false;
            }

            $this->recordAttempt($fresh);

            $delivered = $this->deliver($fresh);

            if ($delivered) {
                Lead::withoutGlobalScopes()
                    ->whereKey($fresh->id)
                    ->update(['distribuidor_notificado_em' => now()]);

                return true;
            }

            $attempts = (int) $fresh->distribuidor_notificacao_tentativas + 1;
            if ($attempts >= self::MAX_ATTEMPTS) {
                Log::critical('[LeadDistributorAlert] Desisti de alertar a distribuidora sobre o lead', [
                    'lead_id' => $fresh->id,
                    'tenant_id' => $fresh->tenant_id,
                    'tentativas' => $attempts,
                ]);
            } else {
                Log::warning('[LeadDistributorAlert] Distribuidora não recebeu o alerta, será reenviado', [
                    'lead_id' => $fresh->id,
                    'tenant_id' => $fresh->tenant_id,
                    'tentativa' => $attempts,
                ]);
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('[LeadDistributorAlert] Erro ao alertar a distribuidora', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * Ponto de entrada dos canais de contato (portal, WhatsApp, Chaves na Mão): só age se o lead
     * já existia. Lead recém-criado é coberto por alert() via LeadObserver.
     */
    public function alertContactOf(Lead $lead, string $source): bool
    {
        if ($lead->wasRecentlyCreated) {
            return false;
        }

        return $this->alertRecontact($lead, $source, $lead->previousContactAt);
    }

    /**
     * Cliente já cadastrado entrou em contato de novo.
     *  - Alexsandra: avisada conforme o modo do tenant (lead_recontato_modo) para decidir se redistribui.
     *  - Corretor atual (se houver): avisado em todo recontato, com intervalo mínimo próprio,
     *    independentemente do filtro da Alexsandra. O modo "desligado" desliga os dois avisos.
     * Retorna true se algum dos dois foi avisado.
     */
    public function alertRecontact(Lead $lead, string $source, ?Carbon $previousContactAt = null): bool
    {
        try {
            $tenantId = (int) $lead->tenant_id;
            if ($tenantId <= 0) {
                return false;
            }

            $mode = (string) AppSetting::getValue('lead_recontato_modo', self::RECONTACT_MODE_FILTERED, $tenantId);
            $days = (int) AppSetting::getValue('lead_recontato_dias', self::DEFAULT_RECONTACT_DAYS, $tenantId);

            $fresh = Lead::withoutGlobalScopes()->find($lead->id);
            if (!$fresh) {
                return false;
            }

            if ($mode === self::RECONTACT_MODE_OFF) {
                return false;
            }

            // Alerta de lead novo ainda pendente: ele já cobre este contato.
            if (!$fresh->distribuidor_notificado_em) {
                return false;
            }

            $now = now();
            $lastContactAt = $this->latestContactAt($fresh, $previousContactAt);
            $broker = $this->resolveCurrentBroker($fresh);

            $distributorAlerted = false;
            $lastAlertAt = $this->latestCarbon($fresh->distribuidor_notificado_em, $fresh->distribuidor_recontato_em);

            if (self::shouldAlertRecontact($mode, $days, $broker !== null, $lastContactAt, $lastAlertAt, $now)) {
                $distributorAlerted = $this->assignmentNotificationService->alertDistributorOfRecontact(
                    $fresh,
                    $source,
                    $lastContactAt,
                    $broker?->name
                );

                if ($distributorAlerted) {
                    Lead::withoutGlobalScopes()
                        ->whereKey($fresh->id)
                        ->update(['distribuidor_recontato_em' => $now]);
                }
            }

            $brokerAlerted = false;
            $distributor = $this->assignmentNotificationService->findDistributionUser((int) $fresh->tenant_id);
            $isDistributor = $broker && $distributor && (int) $broker->id === (int) $distributor->id;

            if ($broker && !$isDistributor && self::cooldownElapsed($fresh->corretor_recontato_em, $now)) {
                $brokerAlerted = $this->assignmentNotificationService->alertBrokerOfRecontact(
                    $fresh,
                    $broker,
                    $source,
                    $lastContactAt
                );

                if ($brokerAlerted) {
                    Lead::withoutGlobalScopes()
                        ->whereKey($fresh->id)
                        ->update(['corretor_recontato_em' => $now]);
                }
            }

            return $distributorAlerted || $brokerAlerted;
        } catch (\Throwable $e) {
            Log::error('[LeadDistributorAlert] Erro ao alertar recontato', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Regra de decisão do aviso de recontato.
     *  - desligado: nunca.
     *  - todos (opção B): sempre, exceto dentro do intervalo mínimo desde o último aviso.
     *  - filtrado (opção C): só se o lead não tem corretor OU o contato anterior foi há mais
     *    de $days dias (ou nunca houve), também respeitando o intervalo mínimo.
     */
    public static function shouldAlertRecontact(
        string $mode,
        int $days,
        bool $hasBroker,
        ?Carbon $lastContactAt,
        ?Carbon $lastAlertAt,
        Carbon $now
    ): bool {
        if ($mode === self::RECONTACT_MODE_OFF) {
            return false;
        }

        if (!self::cooldownElapsed($lastAlertAt, $now)) {
            return false;
        }

        if ($mode === self::RECONTACT_MODE_ALL) {
            return true;
        }

        // Qualquer valor desconhecido cai no padrão (filtrado).
        $days = $days > 0 ? $days : self::DEFAULT_RECONTACT_DAYS;

        return !$hasBroker
            || $lastContactAt === null
            || $lastContactAt->diffInDays($now, true) >= $days;
    }

    public static function cooldownElapsed(?Carbon $lastAt, Carbon $now): bool
    {
        return $lastAt === null || $lastAt->diffInMinutes($now, true) >= self::RECONTACT_COOLDOWN_MINUTES;
    }

    /**
     * Corretor que atende o cliente agora: o do lead ou, se vazio, o da conversa mais recente
     * que tenha corretor (a redistribuição pelo chat grava na conversa, não no lead).
     */
    private function resolveCurrentBroker(Lead $lead): ?User
    {
        $brokerId = $lead->corretor_id ?: DB::table('conversas')
            ->where('lead_id', $lead->id)
            ->whereNotNull('corretor_id')
            ->orderByDesc('ultima_atividade')
            ->value('corretor_id');

        return $brokerId
            ? User::query()->where('tenant_id', $lead->tenant_id)->whereKey($brokerId)->first()
            : null;
    }

    /**
     * Último contato anterior ao atual: o maior entre o informado e a última mensagem
     * registrada nas conversas do lead (a ultima_interacao não é atualizada pelo WhatsApp).
     * O contato atual não conta: a mensagem que acabou de chegar não pode zerar o "parado há X dias".
     */
    private function latestContactAt(Lead $lead, ?Carbon $previousContactAt): ?Carbon
    {
        $lastMessage = DB::table('mensagens')
            ->join('conversas', 'conversas.id', '=', 'mensagens.conversa_id')
            ->where('conversas.lead_id', $lead->id)
            ->where('mensagens.created_at', '<', now()->subMinutes(self::RECONTACT_COOLDOWN_MINUTES))
            ->max('mensagens.created_at');

        return $this->latestCarbon(
            $previousContactAt,
            $lastMessage ? Carbon::parse($lastMessage) : null
        );
    }

    private function latestCarbon(?Carbon $a, ?Carbon $b): ?Carbon
    {
        if ($a && $b) {
            return $a->greaterThan($b) ? $a : $b;
        }

        return $a ?? $b;
    }

    /**
     * Novo contato de um lead que já existia e cuja distribuidora nunca foi avisada pelo
     * handoff (ex.: lead do portal que depois chega pelo Chaves na Mão). Reabre o controle
     * e alerta mesmo que o lead seja antigo.
     */
    public function reopenAndAlert(Lead $lead): bool
    {
        // Só reabre quem foi marcado sem nunca ter recebido o handoff (lead pré-existente).
        // Se o marcador está vazio, o alerta já está pendente/em andamento e basta enviá-lo.
        if ($lead->distribuidor_notificado_em && empty($lead->atendimento_notificado_em)) {
            Lead::withoutGlobalScopes()
                ->whereKey($lead->id)
                ->update(['distribuidor_notificado_em' => null, 'distribuidor_notificacao_tentativas' => 0]);

            $lead->distribuidor_notificado_em = null;
            $lead->distribuidor_notificacao_tentativas = 0;
        }

        return $this->alert($lead, true);
    }

    private function deliver(Lead $lead): bool
    {

        if (!$this->handoffNotificationService->isHandoffLead($lead)) {
            return $this->assignmentNotificationService->alertDistributorOfNewLead($lead);
        }

        // Chaves na Mão / mascote: aviso in-app + handoff dedicado (Alexsandra e Roberto).
        // O lead só conta como alertado quando a Alexsandra especificamente recebeu: o
        // sucesso do Roberto não pode mascarar uma falha no envio dela.
        $this->assignmentNotificationService->alertDistributorOfNewLead($lead, false);

        $notified = $this->handoffNotificationService->notify($lead);

        return in_array(self::DISTRIBUTOR_NAME, $notified, true);
    }

    private function isEligible(Lead $lead, bool $ignoreAge): bool
    {
        if (!$ignoreAge && (int) $lead->distribuidor_notificacao_tentativas >= self::MAX_ATTEMPTS) {
            return false;
        }

        // Leads antigos não entram: evita alertas retroativos (ex.: ao reativar o fluxo).
        return $ignoreAge
            || $lead->created_at === null
            || $lead->created_at->greaterThanOrEqualTo(now()->subHours(self::MAX_AGE_HOURS));
    }

    private function recordAttempt(Lead $lead): void
    {
        Lead::withoutGlobalScopes()
            ->whereKey($lead->id)
            ->increment('distribuidor_notificacao_tentativas');
    }

    /**
     * @return \Illuminate\Contracts\Cache\Lock|false|null  Lock obtido, false se já está em uso,
     *                                                       null se o driver de cache não suporta locks.
     */
    private function acquireLock(int $leadId)
    {
        try {
            $lock = Cache::lock("lead-distributor-alert:{$leadId}", 90);

            return $lock->get() ? $lock : false;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
