<?php

namespace App\Services;

use App\Models\Conversa;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\TenantConfig;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ConversationAssignmentNotificationService
{
    private const TENANT_ONE_DISTRIBUTOR = 'alexsandra';

    public function notifyAwaitingDistribution(Conversa $conversa, ?Lead $lead = null): ?Notification
    {
        $tenantId = (int) ($conversa->tenant_id ?: $lead?->tenant_id);
        if ($tenantId <= 0) {
            return null;
        }

        $distributor = $this->resolveDistributionUser($tenantId);
        if (!$distributor) {
            Log::warning('[ConversationAssignmentNotification] Nenhum distribuidor ativo encontrado', [
                'tenant_id' => $tenantId,
                'conversa_id' => $conversa->id,
                'lead_id' => $lead?->id ?: $conversa->lead_id,
            ]);

            return null;
        }

        return $this->createConversationNotification(
            $distributor,
            $conversa,
            $lead,
            'awaiting_distribution',
            'Lead pronto para distribuição',
            $this->buildDistributionMessage($lead, $conversa)
        );
    }

    public function notifyAiConversationStarted(Conversa $conversa, ?Lead $lead = null): ?Notification
    {
        $tenantId = (int) ($conversa->tenant_id ?: $lead?->tenant_id);
        if ($tenantId <= 0) {
            return null;
        }

        $distributor = $this->resolveDistributionUser($tenantId);
        if (!$distributor) {
            Log::warning('[ConversationAssignmentNotification] Nenhum distribuidor ativo encontrado para novo contato IA', [
                'tenant_id' => $tenantId,
                'conversa_id' => $conversa->id,
                'lead_id' => $lead?->id ?: $conversa->lead_id,
            ]);

            return null;
        }

        return $this->createConversationNotification(
            $distributor,
            $conversa,
            $lead,
            'ai_conversation_started',
            'Novo contato com a IA',
            $this->buildAiStartedMessage($lead, $conversa)
        );
    }

    public function notifyLeadCreated(Lead $lead): ?Notification
    {
        $tenantId = (int) $lead->tenant_id;
        if ($tenantId <= 0) {
            return null;
        }

        $distributor = $this->resolveDistributionUser($tenantId);
        if (!$distributor) {
            Log::warning('[ConversationAssignmentNotification] Nenhum distribuidor ativo encontrado para lead criado', [
                'tenant_id' => $tenantId,
                'lead_id' => $lead->id,
            ]);

            return null;
        }

        return $this->createLeadNotification(
            $distributor,
            $lead,
            'lead_created',
            'Novo lead criado',
            $this->buildLeadCreatedMessage($lead)
        );
    }

    public function notifyAssigned(Conversa $conversa, User $assignee, ?Lead $lead = null, ?User $assignedBy = null): ?Notification
    {
        $tenantId = (int) ($conversa->tenant_id ?: $lead?->tenant_id ?: $assignee->tenant_id);
        if ($tenantId <= 0 || (int) $assignee->tenant_id !== $tenantId) {
            return null;
        }

        return $this->createConversationNotification(
            $assignee,
            $conversa,
            $lead,
            'conversation_assigned',
            'Novo atendimento atribuído',
            $this->buildAssignedMessage($lead, $conversa, $assignedBy),
            ['assigned_by_user_id' => $assignedBy?->id]
        );
    }

    private function resolveDistributionUser(int $tenantId): ?User
    {
        $baseQuery = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('role', ['admin', 'super_admin']);

        if ($tenantId === 1) {
            $alexsandra = (clone $baseQuery)
                ->where(function ($query) {
                    $query->whereRaw('LOWER(name) LIKE ?', ['%' . self::TENANT_ONE_DISTRIBUTOR . '%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', ['%' . self::TENANT_ONE_DISTRIBUTOR . '%']);
                })
                ->orderByRaw("CASE WHEN LOWER(email) LIKE ? THEN 0 ELSE 1 END", ['%' . self::TENANT_ONE_DISTRIBUTOR . '%'])
                ->orderBy('id')
                ->first();

            if ($alexsandra) {
                return $alexsandra;
            }
        }

        return $baseQuery->orderBy('id')->first();
    }

    private function createConversationNotification(
        User $user,
        Conversa $conversa,
        ?Lead $lead,
        string $event,
        string $title,
        string $message,
        array $extraData = []
    ): Notification {
        $leadId = $lead?->id ?: $conversa->lead_id;
        $actionUrl = "/chat?conversationId={$conversa->id}";

        $existing = Notification::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('type', 'message')
            ->where('channel', 'in_app')
            ->where('action_url', $actionUrl)
            ->where('is_read', false)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($event) {
                $query->where('data->event', $event)
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.event')) = ?", [$event]);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $notification = Notification::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => 'message',
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => array_filter(array_merge([
                'event' => $event,
                'conversa_id' => (int) $conversa->id,
                'lead_id' => $leadId ? (int) $leadId : null,
                'lead_nome' => $this->leadName($lead),
                'telefone' => $lead?->telefone ?: $conversa->telefone,
                'action_url' => $actionUrl,
            ], $extraData), fn ($value) => $value !== null && $value !== ''),
            'channel' => 'in_app',
            'is_read' => false,
            'is_sent' => true,
            'sent_at' => Carbon::now(),
        ]);

        $this->sendWhatsAppOperationalAlert($user, $conversa, $lead, $event, $title, $message);

        return $notification;
    }

    private function createLeadNotification(
        User $user,
        Lead $lead,
        string $event,
        string $title,
        string $message
    ): Notification {
        $actionUrl = "/chat?leadId={$lead->id}";

        $existing = Notification::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('type', 'message')
            ->where('channel', 'in_app')
            ->where('action_url', $actionUrl)
            ->where('is_read', false)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($event) {
                $query->where('data->event', $event)
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.event')) = ?", [$event]);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $notification = Notification::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => 'message',
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => array_filter([
                'event' => $event,
                'lead_id' => (int) $lead->id,
                'lead_nome' => $this->leadName($lead),
                'telefone' => $lead->telefone ?: $lead->whatsapp,
                'action_url' => $actionUrl,
            ], fn ($value) => $value !== null && $value !== ''),
            'channel' => 'in_app',
            'is_read' => false,
            'is_sent' => true,
            'sent_at' => Carbon::now(),
        ]);

        $this->sendLeadCreatedWhatsAppOperationalAlert($user, $lead, $title, $message);

        return $notification;
    }

    private function buildDistributionMessage(?Lead $lead, Conversa $conversa): string
    {
        $name = $this->leadName($lead);
        $phone = $lead?->telefone ?: $conversa->telefone;

        return trim($name . ($phone ? " ({$phone})" : '') . ' está aguardando distribuição no chat.');
    }

    private function buildAiStartedMessage(?Lead $lead, Conversa $conversa): string
    {
        $name = $this->leadName($lead);
        $phone = $lead?->telefone ?: $conversa->telefone;

        return trim($name . ($phone ? " ({$phone})" : '') . ' iniciou conversa com a IA.');
    }

    private function buildLeadCreatedMessage(Lead $lead): string
    {
        $name = $this->leadName($lead);
        $phone = $lead->telefone ?: $lead->whatsapp;
        $origin = $this->leadOrigin($lead);
        $interest = $this->leadInterestSummary($lead);
        $originText = $origin ? " de origem {$origin}" : '';

        return trim($name . ($phone ? " ({$phone})" : '') . " foi criado como novo lead{$originText}." . ($interest ? " Interesse: {$interest}." : ''));
    }

    private function sendWhatsAppOperationalAlert(
        User $user,
        Conversa $conversa,
        ?Lead $lead,
        string $event,
        string $title,
        string $message
    ): void {
        if (!in_array($event, ['ai_conversation_started', 'awaiting_distribution'], true)) {
            return;
        }

        $phone = $this->resolveOperationalWhatsAppNumber($user);
        if (!$phone) {
            Log::warning('[ConversationAssignmentNotification] WhatsApp operacional nao configurado', [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'event' => $event,
                'conversa_id' => $conversa->id,
            ]);

            return;
        }

        $body = $this->buildOperationalWhatsAppMessage($event, $title, $message, $lead, $conversa);
        $result = $this->sendWhatsAppMessage((int) $user->tenant_id, $phone, $body);

        if (!($result['success'] ?? false)) {
            Log::warning('[ConversationAssignmentNotification] Falha ao enviar WhatsApp operacional', [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'event' => $event,
                'conversa_id' => $conversa->id,
                'error' => $result['error'] ?? null,
                'http_code' => $result['http_code'] ?? null,
            ]);
        }
    }

    private function sendLeadCreatedWhatsAppOperationalAlert(User $user, Lead $lead, string $title, string $message): void
    {
        $phone = $this->resolveOperationalWhatsAppNumber($user);
        if (!$phone) {
            Log::warning('[ConversationAssignmentNotification] WhatsApp operacional nao configurado para lead criado', [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'lead_id' => $lead->id,
            ]);

            return;
        }

        $body = $this->buildLeadCreatedOperationalWhatsAppMessage($title, $message, $lead);
        $result = $this->sendWhatsAppMessage((int) $user->tenant_id, $phone, $body);

        if (!($result['success'] ?? false)) {
            Log::warning('[ConversationAssignmentNotification] Falha ao enviar WhatsApp operacional de lead criado', [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'lead_id' => $lead->id,
                'error' => $result['error'] ?? null,
                'http_code' => $result['http_code'] ?? null,
            ]);
        }
    }

    private function resolveOperationalWhatsAppNumber(User $user): ?string
    {
        $tenantId = (int) $user->tenant_id;
        $envKeys = [
            "TENANT_{$tenantId}_AI_ADMIN_WHATSAPP_NUMBER",
            "TENANT_{$tenantId}_OPERATIONAL_WHATSAPP_NUMBER",
            'AI_ADMIN_WHATSAPP_NUMBER',
            'OPERATIONAL_WHATSAPP_NUMBER',
            'EXCLUSIVA_ALEXSANDRA_WHATSAPP',
        ];

        foreach ($envKeys as $key) {
            $value = trim((string) env($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        $config = TenantConfig::where('tenant_id', $tenantId)->first();
        $metadata = is_array($config?->metadata) ? $config->metadata : [];

        foreach (['ai_admin_whatsapp_number', 'operational_whatsapp_number', 'alexsandra_whatsapp'] as $key) {
            $value = trim((string) ($metadata[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $user->loadMissing('pessoa');
        foreach ([$user->pessoa?->whatsapp, $user->pessoa?->celular, $user->pessoa?->telefone] as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        $tenantWhatsApp = trim((string) $config?->whatsapp_number);
        if ($tenantWhatsApp !== '') {
            return $tenantWhatsApp;
        }

        return null;
    }

    private function buildOperationalWhatsAppMessage(
        string $event,
        string $title,
        string $message,
        ?Lead $lead,
        Conversa $conversa
    ): string {
        $name = $this->leadName($lead);
        $phone = $lead?->telefone ?: $lead?->whatsapp ?: $conversa->telefone;
        $budget = $lead?->budget_max ? 'até R$ ' . number_format((float) $lead->budget_max, 0, ',', '.') : null;
        $location = $lead?->preferencia_bairro ?: $lead?->localizacao;
        $rooms = $lead?->quartos ? "{$lead->quartos} quartos" : null;
        $criteria = implode(', ', array_filter([$location, $budget, $rooms]));
        $criteriaLine = $criteria ? "\nCritérios: {$criteria}" : '';
        $actionUrl = rtrim((string) config('app.url'), '/') . "/chat?conversationId={$conversa->id}";

        $headline = $event === 'ai_conversation_started'
            ? 'A Teresa iniciou um atendimento'
            : 'A Teresa precisa direcionar para atendimento humano';

        return "{$headline}\n"
            . "Cliente: {$name}" . ($phone ? " ({$phone})" : '') . "\n"
            . "Resumo: {$message}{$criteriaLine}\n"
            . "Abrir chat: {$actionUrl}";
    }

    private function buildLeadCreatedOperationalWhatsAppMessage(string $title, string $message, Lead $lead): string
    {
        $name = $this->leadName($lead);
        $phone = $lead->telefone ?: $lead->whatsapp;
        $origin = $this->leadOrigin($lead);
        $interest = $this->leadInterestSummary($lead);
        $criteria = $this->leadCriteriaSummary($lead);
        $actionUrl = rtrim((string) config('app.url'), '/') . "/chat?leadId={$lead->id}";

        return "Novo lead criado\n"
            . "Cliente: {$name}" . ($phone ? " ({$phone})" : '') . "\n"
            . "Origem: " . ($origin ?: 'não informada') . "\n"
            . "Resumo: {$message}"
            . ($interest ? "\nInteresse: {$interest}" : '')
            . ($criteria ? "\nCritérios: {$criteria}" : '')
            . "\nAbrir chat: {$actionUrl}";
    }

    private function sendWhatsAppMessage(int $tenantId, string $phone, string $body): array
    {
        $driver = strtolower((string) config('whatsapp.driver', 'evolution'));

        try {
            if ($driver === 'meta_cloud') {
                return app(MetaCloudGateway::class)->sendMessage($phone, $body, $tenantId);
            }

            if ($driver === 'evolution') {
                return app(EvolutionApiService::class)->sendMessage($phone, $body);
            }

            $config = TenantConfig::where('tenant_id', $tenantId)->first();

            return app(TwilioService::class)->sendMessage(
                $phone,
                $body,
                $config?->twilio_whatsapp_from,
                $config?->twilio_account_sid,
                $config?->twilio_auth_token
            );
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function buildAssignedMessage(?Lead $lead, Conversa $conversa, ?User $assignedBy): string
    {
        $name = $this->leadName($lead);
        $suffix = $assignedBy ? " por {$assignedBy->name}" : '';

        return "{$name} foi atribuído a você{$suffix}. Abra o chat para continuar o atendimento.";
    }

    private function leadName(?Lead $lead): string
    {
        $name = trim((string) ($lead?->nome ?: $lead?->whatsapp_name));

        return $name !== '' ? $name : 'Cliente';
    }

    private function leadOrigin(Lead $lead): string
    {
        $observations = (string) $lead->observacoes;
        if (stripos($observations, 'Chaves') !== false) {
            return 'Chaves na Mão';
        }

        return trim((string) ($lead->origem ?? ''));
    }

    private function leadInterestSummary(Lead $lead): string
    {
        $observations = html_entity_decode(strip_tags((string) $lead->observacoes), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $observations = preg_replace('/\s+/u', ' ', $observations) ?? $observations;

        if (preg_match('/Mensagem:\s*(.+?)(?:\s+Origem:|\s+Im[oó]vel:|\s+Refer[êe]ncia:|\s+An[uú]ncio:|$)/iu', $observations, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/An[uú]ncio:\s*(.+)$/iu', $observations, $matches)) {
            return trim($matches[1]);
        }

        if (!empty($lead->observacoes_cliente)) {
            return trim((string) $lead->observacoes_cliente);
        }

        return '';
    }

    private function leadCriteriaSummary(Lead $lead): string
    {
        $budget = $lead->budget_max ? 'até R$ ' . number_format((float) $lead->budget_max, 0, ',', '.') : null;
        $location = $lead->preferencia_bairro ?: $lead->localizacao;
        $rooms = $lead->quartos ? "{$lead->quartos} quartos" : null;
        $type = $lead->preferencia_tipo_imovel;

        return implode(', ', array_filter([$location, $type, $budget, $rooms]));
    }
}
