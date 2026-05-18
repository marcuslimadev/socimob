<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class VistoriaTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'vistoria_templates';

    protected $fillable = ['tenant_id', 'nome', 'tipo_vistoria', 'conteudo_json', 'ativo'];

    protected $casts = [
        'conteudo_json' => 'array',
        'ativo' => 'boolean',
    ];
}
