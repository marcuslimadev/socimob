<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VistoriaHistorico extends Model
{
    public $timestamps = false;

    protected $table = 'vistoria_historicos';

    protected $fillable = [
        'vistoria_id', 'user_id', 'acao', 'descricao', 'dados_antes_json',
        'dados_depois_json', 'ip', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'dados_antes_json' => 'array',
        'dados_depois_json' => 'array',
        'created_at' => 'datetime',
    ];
}
