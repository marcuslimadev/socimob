<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientIntention extends Model
{
    use SoftDeletes;

    protected $table = 'client_intentions';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'name',
        'email',
        'phone',
        'whatsapp',
        'type',
        'min_bedrooms',
        'max_bedrooms',
        'min_bathrooms',
        'max_bathrooms',
        'min_price',
        'max_price',
        'min_area',
        'max_area',
        'city',
        'neighborhood',
        'neighborhoods',
        'features',
        'observations',
        'status',
        'paused_at',
        'completed_at',
        'canceled_at',
        'notify_by_email',
        'notify_by_whatsapp',
        'notify_by_sms',
        'metadata',
    ];

    protected $casts = [
        'neighborhoods' => 'json',
        'features' => 'json',
        'metadata' => 'json',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'paused_at' => 'datetime',
        'completed_at' => 'datetime',
        'canceled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relacionamentos
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'intention_id');
    }

    /**
     * Scopes
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ativa');
    }

    public function scopePaused($query)
    {
        return $query->where('status', 'pausada');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'concluida');
    }

    public function scopeCanceled($query)
    {
        return $query->where('status', 'cancelada');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCity($query, $city)
    {
        return $query->where('city', $city);
    }

    public function scopeByNeighborhood($query, $neighborhood)
    {
        return $query->whereJsonContains('neighborhoods', $neighborhood);
    }

    public function scopeByPriceRange($query, $minPrice, $maxPrice)
    {
        return $query->whereBetween('min_price', [$minPrice, $maxPrice])
            ->orWhereBetween('max_price', [$minPrice, $maxPrice]);
    }

    /**
     * Métodos auxiliares
     */
    public function isActive(): bool
    {
        return $this->status === 'ativa';
    }

    public function isPaused(): bool
    {
        return $this->status === 'pausada';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'concluida';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'cancelada';
    }

    public function pause(): void
    {
        $this->update([
            'status' => 'pausada',
            'paused_at' => now(),
        ]);
    }

    public function resume(): void
    {
        $this->update([
            'status' => 'ativa',
            'paused_at' => null,
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'concluida',
            'completed_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelada',
            'canceled_at' => now(),
        ]);
    }

    public function matchesProperty(Property $property): bool
    {
        // Verificar tipo
        if ($this->type !== $property->finalidade_imovel) {
            return false;
        }

        // Verificar quartos
        if ($this->min_bedrooms && $property->quartos < $this->min_bedrooms) {
            return false;
        }
        if ($this->max_bedrooms && $property->quartos > $this->max_bedrooms) {
            return false;
        }

        // Verificar banheiros
        if ($this->min_bathrooms && $property->banheiros < $this->min_bathrooms) {
            return false;
        }
        if ($this->max_bathrooms && $property->banheiros > $this->max_bathrooms) {
            return false;
        }

        // Verificar preço
        if ($this->min_price && $property->preco < $this->min_price) {
            return false;
        }
        if ($this->max_price && $property->preco > $this->max_price) {
            return false;
        }

        // Verificar área
        if ($this->min_area && $property->area < $this->min_area) {
            return false;
        }
        if ($this->max_area && $property->area > $this->max_area) {
            return false;
        }

        // Verificar localização
        if ($this->city && $property->cidade !== $this->city) {
            return false;
        }

        if ($this->neighborhoods && !in_array($property->bairro, $this->neighborhoods)) {
            return false;
        }

        return true;
    }

    public function getFormattedType(): string
    {
        return match($this->type) {
            'venda' => 'Compra',
            'aluguel' => 'Aluguel',
            default => $this->type,
        };
    }

    public function getFormattedStatus(): string
    {
        return match($this->status) {
            'ativa' => 'Ativa',
            'pausada' => 'Pausada',
            'concluida' => 'Concluída',
            'cancelada' => 'Cancelada',
            default => $this->status,
        };
    }
}
