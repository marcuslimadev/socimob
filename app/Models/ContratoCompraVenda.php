<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContratoCompraVenda extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'contratos_compra_venda';

    protected $fillable = [
        'tenant_id',
        'numero_contrato',
        'imovel_id',
        'vendedor_pessoa_id',
        'comprador_pessoa_id',
        'co_vendedores_ids',
        'co_compradores_ids',
        'status',
        'data_contrato',
        'data_escritura_prevista',
        'data_entrega_chaves',
        'prazo_documentacao_dias',
        'prazo_escritura_dias',
        'prazo_registro_dias',
        'valor_total',
        'valor_sinal',
        'valor_parcela_final',
        'multa_percentual',
        'multa_moratoria_percentual',
        'juros_percentual_mes',
        'corretagem_valor',
        'corretagem_responsavel',
        'intermediadora_nome',
        'intermediadora_documento',
        'intermediadora_fantasia',
        'objeto_descricao',
        'matricula_numero',
        'cartorio_nome',
        'inscricao_cadastral',
        'parcelas_pagamento',
        'clausulas',
        'observacoes',
        'metadata',
        'testemunha_um_nome',
        'testemunha_um_documento',
        'testemunha_um_email',
        'testemunha_dois_nome',
        'testemunha_dois_documento',
        'testemunha_dois_email',
    ];

    protected $appends = [
        'vendedores',
        'compradores',
    ];

    protected $casts = [
        'co_vendedores_ids' => 'array',
        'co_compradores_ids' => 'array',
        'data_contrato' => 'date',
        'data_escritura_prevista' => 'date',
        'data_entrega_chaves' => 'date',
        'prazo_documentacao_dias' => 'integer',
        'prazo_escritura_dias' => 'integer',
        'prazo_registro_dias' => 'integer',
        'valor_total' => 'float',
        'valor_sinal' => 'float',
        'valor_parcela_final' => 'float',
        'multa_percentual' => 'float',
        'multa_moratoria_percentual' => 'float',
        'juros_percentual_mes' => 'float',
        'corretagem_valor' => 'float',
        'parcelas_pagamento' => 'array',
        'clausulas' => 'array',
        'metadata' => 'array',
    ];

    public function imovel()
    {
        return $this->belongsTo(Property::class, 'imovel_id');
    }

    public function vendedor()
    {
        return $this->belongsTo(Pessoa::class, 'vendedor_pessoa_id');
    }

    public function comprador()
    {
        return $this->belongsTo(Pessoa::class, 'comprador_pessoa_id');
    }

    public function documentos()
    {
        return $this->hasMany(ContratoDocumento::class, 'contrato_compra_venda_id');
    }

    public function todosVendedores(): \Illuminate\Support\Collection
    {
        $vendedores = collect();

        if ($this->vendedor) {
            $vendedores->push($this->vendedor);
        }

        if (!empty($this->co_vendedores_ids)) {
            $coVendedores = Pessoa::whereIn('id', $this->co_vendedores_ids)->get();
            $vendedores = $vendedores->merge($coVendedores);
        }

        return $vendedores->unique('id')->values();
    }

    public function todosCompradores(): \Illuminate\Support\Collection
    {
        $compradores = collect();

        if ($this->comprador) {
            $compradores->push($this->comprador);
        }

        if (!empty($this->co_compradores_ids)) {
            $coCompradores = Pessoa::whereIn('id', $this->co_compradores_ids)->get();
            $compradores = $compradores->merge($coCompradores);
        }

        return $compradores->unique('id')->values();
    }

    public function getVendedoresAttribute()
    {
        return $this->todosVendedores();
    }

    public function getCompradoresAttribute()
    {
        return $this->todosCompradores();
    }
}
