<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImovelImagem extends Model
{
    protected $table = 'imoveis_imagens';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'url',
        'destaque',
    ];

    protected $casts = [
        'destaque' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'codigo',
    ];

    public function imovel()
    {
        return $this->belongsTo(Property::class, 'codigo', 'codigo');
    }
}
