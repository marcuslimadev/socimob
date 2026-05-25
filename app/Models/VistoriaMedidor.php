<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VistoriaMedidor extends Model
{
    protected $table = 'vistoria_medidores';

    protected $fillable = [
        'vistoria_id', 'tipo', 'leitura', 'unidade', 'observacoes',
    ];

    public function vistoria()
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_id');
    }

    public function midias()
    {
        return $this->hasMany(VistoriaMidia::class, 'medidor_id')->orderBy('ordem')->orderBy('id');
    }
}
