<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pessoa extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'pessoas';

    protected $fillable = [
        'tenant_id',
        'nome',
        'pais',
        'telefone',
        'celular',
        'email',
        'tipo',
        'cpf',
        'rg',
        'orgao_expedidor',
        'data_expedicao',
        'cnh',
        'data_nascimento',
        'cnpj',
        'razao_social',
        'inscricao_estadual',
        'inscricao_municipal',
        'cep',
        'estado',
        'cidade',
        'bairro',
        'endereco',
        'numero',
        'complemento',
        'contatos',
        'observacoes',
        'ativo',
    ];

    protected $casts = [
        'contatos' => 'array',
        'data_expedicao' => 'date',
        'data_nascimento' => 'date',
        'ativo' => 'boolean',
    ];
}
