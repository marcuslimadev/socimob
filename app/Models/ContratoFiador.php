<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ContratoFiador extends Model
{
    use BelongsToTenant;

    protected $table = 'contrato_fiadores';

    protected $fillable = [
        'tenant_id',
        'contrato_id',
        'pessoa_id',
        'tipo_vinculo',
    ];

    public function contrato()
    {
        return $this->belongsTo(ContratoLocacao::class, 'contrato_id');
    }

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }
}
