<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class VistoriaComentario extends Model
{
    use BelongsToTenant;

    protected $table = 'vistoria_comentarios';

    protected $fillable = [
        'tenant_id',
        'vistoria_id',
        'user_id',
        'pessoa_id',
        'autor_nome',
        'comentario',
    ];

    public function vistoria()
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_id');
    }
}

