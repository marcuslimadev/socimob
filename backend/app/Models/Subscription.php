<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'plan_name',
        'plan_amount',
        'plan_interval',
        'status',
        'status_reason',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'pagar_me_subscription_id',
        'pagar_me_customer_id',
        'pagar_me_card_id',
        'payment_method',
        'card_last_four',
        'card_brand',
        'failed_attempts',
        'next_retry_at',
        'metadata',
    ];

    protected $casts = [
        'plan_amount' => 'decimal:2',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'failed_attempts' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'pagar_me_subscription_id',
        'pagar_me_customer_id',
        'pagar_me_card_id',
    ];

    /**
     * Relacionamentos
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePastDue($query)
    {
        return $query->where('status', 'past_due');
    }

    public function scopeCanceled($query)
    {
        return $query->where('status', 'canceled');
    }

    public function scopeExpiring($query, $days = 7)
    {
        return $query->whereBetween('current_period_end', [
            now(),
            now()->addDays($days),
        ]);
    }

    /**
     * Métodos auxiliares
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function isExpiring($days = 7): bool
    {
        return $this->current_period_end 
            && $this->current_period_end->between(now(), now()->addDays($days));
    }

    public function isExpired(): bool
    {
        return $this->current_period_end && $this->current_period_end->isPast();
    }

    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'status_reason' => $reason,
        ]);

        // Atualizar status do tenant
        $this->tenant()->update([
            'subscription_status' => 'canceled',
        ]);
    }

    public function markAsPastDue(string $reason = null): void
    {
        $this->update([
            'status' => 'past_due',
            'status_reason' => $reason,
            'failed_attempts' => $this->failed_attempts + 1,
            'next_retry_at' => now()->addHours(24),
        ]);
    }

    public function markAsActive(): void
    {
        $this->update([
            'status' => 'active',
            'status_reason' => null,
            'failed_attempts' => 0,
            'next_retry_at' => null,
        ]);

        // Atualizar status do tenant
        $this->tenant()->update([
            'subscription_status' => 'active',
        ]);
    }

    public function updatePeriod($startDate, $endDate): void
    {
        $this->update([
            'current_period_start' => $startDate,
            'current_period_end' => $endDate,
        ]);
    }

    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->current_period_end) {
            return null;
        }

        return now()->diffInDays($this->current_period_end, false);
    }

    public function getFormattedAmount(): string
    {
        return 'R$ ' . number_format($this->plan_amount, 2, ',', '.');
    }

    public function getFormattedInterval(): string
    {
        return match($this->plan_interval) {
            'month' => 'Mensal',
            'year' => 'Anual',
            default => $this->plan_interval,
        };
    }
}
