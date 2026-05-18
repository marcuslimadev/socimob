<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VistoriaItem extends Model
{
    protected $table = 'vistoria_itens';

    protected $fillable = [
        'vistoria_ambiente_id', 'nome', 'descricao', 'estado',
        'possui_inconformidade', 'observacoes', 'ordem',
    ];

    protected $casts = [
        'possui_inconformidade' => 'boolean',
        'ordem' => 'integer',
    ];

    public function ambiente()
    {
        return $this->belongsTo(VistoriaAmbiente::class, 'vistoria_ambiente_id');
    }
}
