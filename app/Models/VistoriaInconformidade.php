<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VistoriaInconformidade extends Model
{
    protected $table = 'vistoria_inconformidades';

    protected $fillable = [
        'vistoria_id', 'ambiente_id', 'item_id', 'descricao', 'severidade',
        'responsabilidade_sugerida', 'status',
    ];

    public function vistoria()
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_id');
    }

    public function ambiente()
    {
        return $this->belongsTo(VistoriaAmbiente::class, 'ambiente_id');
    }

    public function midias()
    {
        return $this->hasMany(VistoriaMidia::class, 'inconformidade_id')->orderBy('ordem')->orderBy('id');
    }
}
