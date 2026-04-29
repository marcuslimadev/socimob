<?php

namespace App\Services;

use App\Models\Conversa;
use App\Models\Lead;
use App\Models\Notification;
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
            ->first();

        if ($existing) {
            return $existing;
        }

        return Notification::create([
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
    }

    private function buildDistributionMessage(?Lead $lead, Conversa $conversa): string
    {
        $name = $this->leadName($lead);
        $phone = $lead?->telefone ?: $conversa->telefone;

        return trim($name . ($phone ? " ({$phone})" : '') . ' está aguardando distribuição no chat.');
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
}
