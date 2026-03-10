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
        'contrato_id',
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
        'comodos',
        'assinatura_inquilino_status',
        'assinatura_proprietario_status',
    ];

    public function fotos()
    {
        return $this->hasMany(VistoriaFoto::class, 'vistoria_id')->orderBy('comodo')->orderBy('ordem');
    }

    protected $casts = [
        'vistoriadores' => 'array',
        'pessoas' => 'array',
        'metragem' => 'decimal:2',
        'mobiliado' => 'boolean',
        'data_vistoria' => 'datetime',
    ];
}
