<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChamadoOperacional extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'chamados_operacionais';

    protected $fillable = [
        'tenant_id',
        'contrato_id',
        'cobranca_id',
        'aberto_por_pessoa_id',
        'responsavel_user_id',
        'protocolo',
        'assunto',
        'categoria',
        'prioridade',
        'status',
        'descricao',
        'primeira_resposta_em',
        'resolvido_em',
        'metadata',
    ];

    protected $casts = [
        'primeira_resposta_em' => 'datetime',
        'resolvido_em' => 'datetime',
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

    public function mensagens()
    {
        return $this->hasMany(ChamadoMensagem::class, 'chamado_id');
    }

    public function anexos()
    {
        return $this->hasMany(ChamadoAnexo::class, 'chamado_id');
    }
}
