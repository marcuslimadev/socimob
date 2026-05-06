<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VistoriaSolicitacao extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'vistoria_solicitacoes';

    protected $fillable = [
        'tenant_id',
        'codigo',
        'status',
        'cliente_nome',
        'tipo',
        'imovel_id',
        'vistoria_id',
        'observacoes',
        'pessoas',
        'historico',
    ];

    protected $casts = [
        'pessoas' => 'array',
        'historico' => 'array',
    ];

    public function vistoria()
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_id');
    }
}
