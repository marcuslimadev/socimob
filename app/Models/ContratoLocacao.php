<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContratoLocacao extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'contratos_locacao';

    protected $fillable = [
        'tenant_id',
        'imovel_id',
        'locador_pessoa_id',
        'locatario_pessoa_id',
        'status',
        'inicio',
        'fim',
        'dia_vencimento',
        'valor_aluguel',
        'valor_condominio',
        'valor_iptu',
        'valor_taxa',
        'valor_seguro',
        'indice_reajuste',
        'periodicidade_reajuste',
        'proximo_reajuste',
        'metadata',
    ];

    protected $casts = [
        'inicio' => 'date',
        'fim' => 'date',
        'proximo_reajuste' => 'date',
        'valor_aluguel' => 'float',
        'valor_condominio' => 'float',
        'valor_iptu' => 'float',
        'valor_taxa' => 'float',
        'valor_seguro' => 'float',
        'metadata' => 'array',
    ];

    public function imovel()
    {
        return $this->belongsTo(Property::class, 'imovel_id');
    }

    public function locador()
    {
        return $this->belongsTo(Pessoa::class, 'locador_pessoa_id');
    }

    public function locatario()
    {
        return $this->belongsTo(Pessoa::class, 'locatario_pessoa_id');
    }

    public function cobrancas()
    {
        return $this->hasMany(CobrancaContrato::class, 'contrato_id');
    }
}
