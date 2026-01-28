<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vistoria extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'vistorias';

    protected $fillable = [
        'tenant_id',
        'codigo',
        'status',
        'cliente_nome',
        'imovel_id',
        'tipo',
        'vistoriadores',
        'pessoas',
        'metragem',
        'mobiliado',
        'data_vistoria',
        'observacoes',
    ];

    protected $casts = [
        'vistoriadores' => 'array',
        'pessoas' => 'array',
        'metragem' => 'decimal:2',
        'mobiliado' => 'boolean',
        'data_vistoria' => 'datetime',
    ];
}
