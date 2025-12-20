<?php

namespace App\Models\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->bound('tenant')) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', app('tenant')->id);
            }
        });

        static::creating(function ($model) {
            if (app()->bound('tenant') && empty($model->tenant_id)) {
                $model->tenant_id = app('tenant')->id;
            }
        });
    }

    /**
     * Relacionamento com o tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Scope para filtrar por tenant específico
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->withoutGlobalScope('tenant')->where('tenant_id', $tenantId);
    }

    /**
     * Scope para remover filtro de tenant
     */
    public function scopeWithoutTenant($query)
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Verifica se pertence ao tenant atual
     */
    public function belongsToCurrentTenant(): bool
    {
        if (!app()->bound('tenant')) {
            return false;
        }

        return $this->tenant_id === app('tenant')->id;
    }
}
