<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionInvoice extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'corretor_id',
        'lead_id',
        'property_id',
        'valor_total',
        'aliquota_iss',
        'valor_iss',
        'descricao_servico',
        'competencia',
        'status',
        'nfse_numero',
        'nfse_codigo_verificacao',
        'nfse_rps',
        'nfse_pdf_url',
        'nfse_xml_url',
        'integracao_id',
        'tomador_dados',
        'financeiro_metadata',
        'retorno_integracao',
        'financeiro_status',
        'financeiro_vencimento',
        'financeiro_liquidacao',
        'erro_integracao',
    ];

    protected $casts = [
        'valor_total' => 'float',
        'aliquota_iss' => 'float',
        'valor_iss' => 'float',
        'competencia' => 'date',
        'financeiro_vencimento' => 'date',
        'financeiro_liquidacao' => 'date',
        'tomador_dados' => 'array',
        'financeiro_metadata' => 'array',
        'retorno_integracao' => 'array',
    ];

    public function corretor()
    {
        return $this->belongsTo(User::class, 'corretor_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function financialTransaction()
    {
        return $this->hasOne(FinancialTransaction::class, 'commission_invoice_id');
    }

    public function markAsIssued(array $nfseData): void
    {
        $this->update([
            'status' => 'issued',
            'nfse_numero' => $nfseData['nfse_numero'] ?? null,
            'nfse_codigo_verificacao' => $nfseData['codigo_verificacao'] ?? null,
            'nfse_rps' => $nfseData['nfse_rps'] ?? null,
            'nfse_pdf_url' => $nfseData['pdf_url'] ?? null,
            'nfse_xml_url' => $nfseData['xml_url'] ?? null,
            'integracao_id' => $nfseData['integracao_id'] ?? null,
            'retorno_integracao' => $nfseData['raw_response'] ?? null,
            'erro_integracao' => null,
        ]);
    }

    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => 'error',
            'erro_integracao' => $message,
        ]);
    }

    public function syncFinanceStatus(string $status): void
    {
        $this->update([
            'financeiro_status' => $status,
        ]);
    }
}
