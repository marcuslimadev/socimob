<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepasseProprietario extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'repasses_proprietario';

    protected $fillable = [
        'tenant_id',
        'contrato_id',
        'cobranca_id',
        'competencia',
        'valor_aluguel_recebido',
        'valor_taxa_administracao',
        'valor_deducoes',
        'valor_repasse',
        'status',
        'data_pagamento',
        'forma_pagamento',
        'observacoes',
        'metadata',
    ];

    protected $casts = [
        'valor_aluguel_recebido' => 'float',
        'valor_taxa_administracao' => 'float',
        'valor_deducoes' => 'float',
        'valor_repasse' => 'float',
        'data_pagamento' => 'date',
        'metadata' => 'array',
    ];

    public function contrato()
    {
        return $this->belongsTo(ContratoLocacao::class, 'contrato_id');
    }

    public function cobranca()
    {
        return $this->belongsTo(CobrancaContrato::class, 'cobranca_id');
    }

    public function isPago(): bool
    {
        return $this->status === 'pago';
    }
}
