<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VistoriaAmbiente extends Model
{
    protected $table = 'vistoria_ambientes';

    protected $fillable = [
        'vistoria_id', 'nome', 'ordem', 'estado_geral', 'pintura_estado',
        'limpeza_estado', 'observacoes',
    ];

    protected $casts = ['ordem' => 'integer'];

    public function vistoria()
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_id');
    }

    public function itens()
    {
        return $this->hasMany(VistoriaItem::class, 'vistoria_ambiente_id')->orderBy('ordem')->orderBy('id');
    }

    public function midias()
    {
        return $this->hasMany(VistoriaMidia::class, 'ambiente_id')->orderBy('ordem')->orderBy('id');
    }

    public function inconformidades()
    {
        return $this->hasMany(VistoriaInconformidade::class, 'ambiente_id')->orderByDesc('id');
    }
}
