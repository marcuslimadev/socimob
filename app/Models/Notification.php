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

    public function recordSendAttempt(?string $error = null): void
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
}
