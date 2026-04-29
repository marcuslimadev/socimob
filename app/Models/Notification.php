<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Notification extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'notifications';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'intention_id',
        'property_id',
        'type',
        'title',
        'message',
        'action_url',
        'data',
        'channel',
        'is_read',
        'is_sent',
        'read_at',
        'sent_at',
        'send_attempts',
        'send_error',
        'next_retry_at',
    ];

    protected $casts = [
        'data' => 'json',
        'is_read' => 'boolean',
        'is_sent' => 'boolean',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Notification $notification) {
            $data = self::normalizeDataPayload($notification->data);
            $notification->data = $data;

            $notification->action_url = self::normalizeActionUrl(
                $notification->action_url,
                $notification->type,
                $notification->property_id,
                $notification->intention_id,
                $data,
            );
        });
    }

    /**
     * Relacionamentos
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function intention()
    {
        return $this->belongsTo(ClientIntention::class, 'intention_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Scopes
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeUnsent($query)
    {
        return $query->where('is_sent', false);
    }

    public function scopeSent($query)
    {
        return $query->where('is_sent', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForIntention($query, $intentionId)
    {
        return $query->where('intention_id', $intentionId);
    }

    public function scopeReadyToSend($query)
    {
        return $query->where('is_sent', false)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', Carbon::now());
            });
    }

    /**
     * Métodos auxiliares
     */
    public function isRead(): bool
    {
        return $this->is_read;
    }

    public function isSent(): bool
    {
        return $this->is_sent;
    }

    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => Carbon::now(),
        ]);
    }

    public function markAsUnread(): void
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    public function markAsSent(): void
    {
        $this->update([
            'is_sent' => true,
            'sent_at' => Carbon::now(),
            'send_attempts' => 0,
            'send_error' => null,
            'next_retry_at' => null,
        ]);
    }

    public function recordSendAttempt(string $error = null): void
    {
        $attempts = $this->send_attempts + 1;
        $maxAttempts = 3;

        $nextRetry = null;
        if ($attempts < $maxAttempts) {
            $nextRetry = Carbon::now()->addHours($attempts);
        }

        $this->update([
            'send_attempts' => $attempts,
            'send_error' => $error,
            'next_retry_at' => $nextRetry,
        ]);
    }

    public function getFormattedType(): string
    {
        return match($this->type) {
            'property_match' => 'Imóvel Encontrado',
            'property_new' => 'Novo Imóvel',
            'price_change' => 'Alteração de Preço',
            'status_change' => 'Alteração de Status',
            'message' => 'Mensagem',
            'system' => 'Sistema',
            'vistoria_solicitada' => 'Vistoria Solicitada',
            'vistoria_designada' => 'Vistoria Designada',
            'vistoria_concluida' => 'Vistoria Concluída',
            'assinatura_enviada' => 'Assinatura Enviada',
            'assinatura_assinada' => 'Assinatura Concluída',
            'assinatura_recusada' => 'Assinatura Recusada',
            default => $this->type,
        };
    }

    public function getFormattedChannel(): string
    {
        return match($this->channel) {
            'email' => 'Email',
            'whatsapp' => 'WhatsApp',
            'sms' => 'SMS',
            'push' => 'Notificação Push',
            'in_app' => 'App',
            default => $this->channel,
        };
    }

    public static function inferActionUrl(?string $type, ?int $propertyId = null, ?int $intentionId = null, array $data = []): ?string
    {
        $nestedActionUrl = self::firstString($data, ['action_url']);
        if ($nestedActionUrl) {
            return $nestedActionUrl;
        }

        $leadId = self::firstInt($data, ['lead_id', 'crm_lead_id']);
        $conversationId = self::firstInt($data, ['conversa_id', 'conversation_id']);
        $vistoriaId = self::firstInt($data, ['vistoria_id']);
        $documentId = self::firstInt($data, ['documento_id', 'document_id']);
        $resolvedPropertyId = $propertyId ?: self::firstInt($data, ['property_id', 'current_property_id', 'conflicting_property_id']);
        $registroTipo = self::firstString($data, ['registro_tipo']);
        $notaId = self::firstInt($data, ['nota_id', 'invoice_id']);

        if (in_array($type, ['new_lead', 'lead', 'lead_created'], true)) {
            return $leadId ? "/leads/{$leadId}" : '/leads';
        }

        if (in_array($type, ['message', 'nova_conversa'], true)) {
            if ($conversationId) {
                return "/chat?conversationId={$conversationId}";
            }

            return $leadId ? "/chat?leadId={$leadId}" : '/chat';
        }

        if (is_string($type) && str_starts_with($type, 'vistoria')) {
            return $vistoriaId ? "/vistorias/{$vistoriaId}" : '/vistorias';
        }

        if (is_string($type) && str_starts_with($type, 'assinatura')) {
            return '/assinaturas';
        }

        if ($type === 'property_match' && $resolvedPropertyId) {
            return "/portal/imovel/{$resolvedPropertyId}";
        }

        if (in_array($type, ['property_interest', 'property_new', 'price_change', 'status_change'], true) && $resolvedPropertyId) {
            return "/properties/{$resolvedPropertyId}/editar";
        }

        if ($registroTipo && $notaId) {
            return "/financeiro/notas/{$registroTipo}/{$notaId}";
        }

        if ($resolvedPropertyId) {
            return "/properties/{$resolvedPropertyId}/editar";
        }

        if ($documentId) {
            return '/assinaturas';
        }

        if ($intentionId) {
            return '/crm';
        }

        if (in_array($type, ['system_error', 'security_alert', 'system'], true)) {
            return '/notifications';
        }

        return null;
    }

    public static function normalizeActionUrl(
        ?string $actionUrl,
        ?string $type,
        ?int $propertyId = null,
        ?int $intentionId = null,
        array $data = [],
    ): ?string {
        $normalized = is_string($actionUrl) ? trim($actionUrl) : '';

        if ($normalized === '') {
            return self::inferActionUrl($type, $propertyId, $intentionId, $data);
        }

        if (preg_match('#^/property/(\d+)$#', $normalized, $matches)) {
            return '/portal/imovel/' . $matches[1];
        }

        if (preg_match('#^/properties/(\d+)$#', $normalized, $matches)) {
            return '/properties/' . $matches[1] . '/editar';
        }

        if (preg_match('#^/assinaturas/(\d+)$#', $normalized)) {
            return '/assinaturas';
        }

        return $normalized;
    }

    private static function normalizeDataPayload(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_string($data) && trim($data) !== '') {
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private static function firstInt(array $data, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return null;
    }

    private static function firstString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
