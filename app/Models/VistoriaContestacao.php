<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VistoriaContestacao extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'vistoria_contestacoes';

    protected $fillable = [
        'tenant_id',
        'vistoria_id',
        'codigo',
        'status',
        'tipo',
        'descricao',
        'cliente_nome',
        'imovel_referencia',
        'fotos',
        'documentos',
        'resolucao',
        'data_contestacao',
        'data_resolucao',
        'user_id',
        'historico',
    ];

    protected $casts = [
        'fotos' => 'array',
        'documentos' => 'array',
        'historico' => 'array',
        'data_contestacao' => 'datetime',
        'data_resolucao' => 'datetime',
    ];

    /**
     * Relacionamento com Vistoria
     */
    public function vistoria()
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_id');
    }

    /**
     * Relacionamento com User (responsável)
     */
    public function responsavel()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
