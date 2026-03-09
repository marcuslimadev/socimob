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
        'numero_contrato',
        'imovel_id',
        'locador_pessoa_id',
        'locatario_pessoa_id',
        'status',
        'inicio',
        'fim',
        'rescindido_em',
        'motivo_rescisao',
        'multa_rescisao_calculada',
        'renovado_ate',
        'dia_vencimento',
        'valor_aluguel',
        'valor_condominio',
        'valor_iptu',
        'valor_taxa',
        'valor_seguro',
        'tipo_garantia',
        'valor_garantia',
        'comissao_administracao_percentual',
        'observacoes',
        'clausulas',
        'indice_reajuste',
        'periodicidade_reajuste',
        'proximo_reajuste',
        'metadata',
    ];

    protected $casts = [
        'inicio' => 'date',
        'fim' => 'date',
        'rescindido_em' => 'date',
        'renovado_ate' => 'date',
        'proximo_reajuste' => 'date',
        'valor_aluguel' => 'float',
        'valor_condominio' => 'float',
        'valor_iptu' => 'float',
        'valor_taxa' => 'float',
        'valor_seguro' => 'float',
        'valor_garantia' => 'float',
        'multa_rescisao_calculada' => 'float',
        'comissao_administracao_percentual' => 'float',
        'clausulas' => 'array',
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

    public function fiadores()
    {
        return $this->hasMany(ContratoFiador::class, 'contrato_id');
    }

    public function reajustes()
    {
        return $this->hasMany(ReajusteContrato::class, 'contrato_id');
    }

    public function repasses()
    {
        return $this->hasMany(RepasseProprietario::class, 'contrato_id');
    }

    public function documentos()
    {
        return $this->hasMany(ContratoDocumento::class, 'contrato_id');
    }

    public function vistorias()
    {
        return $this->hasMany(Vistoria::class, 'contrato_id');
    }

    public function isRescindido(): bool
    {
        return $this->rescindido_em !== null;
    }

    public function isAtivo(): bool
    {
        return $this->status === 'ativo' && !$this->isRescindido();
    }
}
