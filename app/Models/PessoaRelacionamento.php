<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PessoaRelacionamento extends Model
{
    use BelongsToTenant;

    protected $table = 'pessoa_relacionamentos';

    protected $fillable = [
        'tenant_id',
        'pessoa_origem_id',
        'pessoa_destino_id',
        'tipo',
        'observacoes',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function pessoaOrigem()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_origem_id');
    }

    public function pessoaDestino()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_destino_id');
    }
}
