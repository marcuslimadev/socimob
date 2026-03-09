<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReajusteContrato extends Model
{
    use BelongsToTenant;

    protected $table = 'reajustes_contrato';

    protected $fillable = [
        'tenant_id',
        'contrato_id',
        'competencia',
        'indice',
        'percentual_aplicado',
        'valor_anterior',
        'valor_novo',
        'observacoes',
        'aplicado_em',
        'aplicado_por_user_id',
    ];

    protected $casts = [
        'percentual_aplicado' => 'float',
        'valor_anterior' => 'float',
        'valor_novo' => 'float',
        'aplicado_em' => 'datetime',
    ];

    public function contrato()
    {
        return $this->belongsTo(ContratoLocacao::class, 'contrato_id');
    }

    public function aplicadoPor()
    {
        return $this->belongsTo(User::class, 'aplicado_por_user_id');
    }
}
