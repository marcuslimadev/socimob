<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VistoriaParte extends Model
{
    protected $table = 'vistoria_partes';

    protected $fillable = [
        'vistoria_id', 'pessoa_id', 'nome', 'documento', 'email', 'telefone', 'funcao',
        'ordem_assinatura', 'assinou', 'data_assinatura', 'assinatura_path',
        'ip_assinatura', 'user_agent_assinatura',
    ];

    protected $casts = [
        'assinou' => 'boolean',
        'data_assinatura' => 'datetime',
    ];

    public function vistoria()
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_id');
    }
}
