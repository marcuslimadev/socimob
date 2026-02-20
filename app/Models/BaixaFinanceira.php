<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BaixaFinanceira extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'baixas_financeiras';

    protected $fillable = [
        'tenant_id',
        'lancamento_id',
        'data_baixa',
        'valor_baixa',
        'meio_pagamento',
        'referencia',
        'status_conciliacao',
        'metadata',
    ];

    protected $casts = [
        'data_baixa' => 'date',
        'valor_baixa' => 'float',
        'metadata' => 'array',
    ];

    public function lancamento()
    {
        return $this->belongsTo(LancamentoFinanceiro::class, 'lancamento_id');
    }
}
