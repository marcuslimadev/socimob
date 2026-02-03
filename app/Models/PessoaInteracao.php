<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PessoaInteracao extends Model
{
    use BelongsToTenant;

    protected $table = 'pessoa_interacoes';

    protected $fillable = [
        'tenant_id',
        'pessoa_id',
        'user_id',
        'tipo',
        'assunto',
        'descricao',
        'metadata',
        'resultado',
        'data_interacao',
        'proxima_acao',
    ];

    protected $casts = [
        'metadata' => 'array',
        'data_interacao' => 'datetime',
        'proxima_acao' => 'datetime',
    ];

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
