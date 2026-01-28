<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentoAssinatura extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'documentos_assinatura';

    protected $fillable = [
        'tenant_id',
        'titulo',
        'tipo',
        'status',
        'descricao',
        'arquivo_url',
        'documento_id_externo',
        'assinantes',
        'lead_id',
        'imovel_id',
        'data_envio',
        'data_conclusao',
        'data_expiracao',
        'historico',
    ];

    protected $casts = [
        'assinantes' => 'array',
        'historico' => 'array',
        'data_envio' => 'datetime',
        'data_conclusao' => 'datetime',
        'data_expiracao' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function imovel()
    {
        return $this->belongsTo(Property::class, 'imovel_id');
    }
}
