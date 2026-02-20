<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LancamentoFinanceiro extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'lancamentos_financeiros';

    protected $fillable = [
        'tenant_id',
        'contrato_id',
        'cobranca_id',
        'pessoa_id',
        'tipo',
        'categoria',
        'descricao',
        'competencia',
        'vencimento',
        'valor',
        'valor_em_aberto',
        'status',
        'metadata',
    ];

    protected $casts = [
        'competencia' => 'date',
        'vencimento' => 'date',
        'valor' => 'float',
        'valor_em_aberto' => 'float',
        'metadata' => 'array',
    ];

    public function contrato()
    {
        return $this->belongsTo(ContratoLocacao::class, 'contrato_id');
    }

    public function cobranca()
    {
        return $this->belongsTo(CobrancaContrato::class, 'cobranca_id');
    }

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function baixas()
    {
        return $this->hasMany(BaixaFinanceira::class, 'lancamento_id');
    }
}
