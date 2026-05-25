<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VistoriaChave extends Model
{
    protected $table = 'vistoria_chaves';

    protected $fillable = ['vistoria_id', 'tipo', 'quantidade', 'estado', 'observacoes'];

    protected $casts = ['quantidade' => 'integer'];

    public function midias()
    {
        return $this->hasMany(VistoriaMidia::class, 'chave_id')->orderBy('ordem')->orderBy('id');
    }
}
