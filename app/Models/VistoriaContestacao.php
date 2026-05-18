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
        'parte_id',
        'codigo',
        'status',
        'tipo',
        'descricao',
        'nome',
        'documento',
        'email',
        'telefone',
        'texto',
        'cliente_nome',
        'imovel_referencia',
        'fotos',
        'documentos',
        'resolucao',
        'data_envio',
        'ip',
        'user_agent',
        'resposta_admin',
        'data_resposta',
        'respondido_por',
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
        'data_envio' => 'datetime',
        'data_resposta' => 'datetime',
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

    public function itens()
    {
        return $this->hasMany(VistoriaContestacaoItem::class, 'contestacao_id');
    }

    public function midias()
    {
        return $this->hasMany(VistoriaContestacaoMidia::class, 'contestacao_id');
    }
}
