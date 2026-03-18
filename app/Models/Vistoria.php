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
        'responsavel_pessoa_id',
        'codigo',
        'status',
        'cliente_nome',
        'imovel_id',
        'tipo',
        'vistoriadores',
        'pessoas',
        'participantes_ids',
        'metragem',
        'mobiliado',
        'data_vistoria',
        'observacoes',
        'comodos',
        'assinatura_inquilino_status',
        'assinatura_proprietario_status',
    ];

    protected $casts = [
        'vistoriadores' => 'array',
        'pessoas' => 'array',
        'participantes_ids' => 'array',
        'comodos' => 'array',
        'metragem' => 'decimal:2',
        'mobiliado' => 'boolean',
        'data_vistoria' => 'datetime',
    ];

    public function fotos()
    {
        return $this->hasMany(VistoriaFoto::class, 'vistoria_id')->orderBy('comodo')->orderBy('ordem');
    }

    public function contrato()
    {
        return $this->belongsTo(ContratoLocacao::class, 'contrato_id');
    }

    public function imovel()
    {
        return $this->belongsTo(Property::class, 'imovel_id');
    }

    public function responsavel()
    {
        return $this->belongsTo(Pessoa::class, 'responsavel_pessoa_id');
    }
}
