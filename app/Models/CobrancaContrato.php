<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CobrancaContrato extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'cobrancas_contrato';

    protected $fillable = [
        'tenant_id',
        'contrato_id',
        'competencia',
        'vencimento',
        'data_emissao',
        'status',
        'valor_base',
        'desconto',
        'multa',
        'juros',
        'valor_total',
        'valor_pago',
        'data_pagamento',
        'forma_pagamento',
        'nosso_numero',
        'linha_digitavel',
        'itens',
        'metadata',
    ];

    protected $casts = [
        'vencimento' => 'date',
        'data_emissao' => 'date',
        'data_pagamento' => 'date',
        'valor_base' => 'float',
        'desconto' => 'float',
        'multa' => 'float',
        'juros' => 'float',
        'valor_total' => 'float',
        'valor_pago' => 'float',
        'itens' => 'array',
        'metadata' => 'array',
    ];

    public function contrato()
    {
        return $this->belongsTo(ContratoLocacao::class, 'contrato_id');
    }

    public function documentoFiscal()
    {
        return $this->hasOne(DocumentoFiscal::class, 'cobranca_id');
    }

    public function lancamentos()
    {
        return $this->hasMany(LancamentoFinanceiro::class, 'cobranca_id');
    }
}
