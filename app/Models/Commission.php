<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Commission extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'corretor_id',
        'valor_venda',
        'percentual',
        'valor_comissao',
        'status',
        'observacoes',
        'mercadopago_payment_id',
        'mercadopago_qrcode',
        'mercadopago_qrcode_base64',
        'pago_em',
        'comprovante_path',
        'nfe_io_id',
        'nfse_numero',
        'nfse_pdf_url',
        'nfse_emitida_em'
    ];

    protected $casts = [
        'valor_venda' => 'decimal:2',
        'percentual' => 'decimal:2',
        'valor_comissao' => 'decimal:2',
        'pago_em' => 'datetime',
        'nfse_emitida_em' => 'datetime'
    ];

    /**
     * Relacionamento com o corretor (User)
     */
    public function corretor()
    {
        return $this->belongsTo(User::class, 'corretor_id');
    }

    /**
     * Relacionamento com o tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Calcular valor da comissão
     */
    public function calcularComissao()
    {
        $this->valor_comissao = ($this->valor_venda * $this->percentual) / 100;
        return $this->valor_comissao;
    }

    /**
     * Marcar como pago
     */
    public function marcarComoPago()
    {
        $this->status = 'pago';
        $this->pago_em = now();
        $this->save();
    }

    /**
     * Verificar se está pago
     */
    public function isPago()
    {
        return $this->status === 'pago';
    }

    /**
     * Verificar se tem NFSe emitida
     */
    public function hasNFSe()
    {
        return !empty($this->nfse_numero);
    }

    /**
     * Scope para filtrar por status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para filtrar por corretor
     */
    public function scopeCorretor($query, $corretorId)
    {
        return $query->where('corretor_id', $corretorId);
    }

    /**
     * Scope para comissões pagas
     */
    public function scopePagas($query)
    {
        return $query->where('status', 'pago');
    }

    /**
     * Scope para comissões pendentes
     */
    public function scopePendentes($query)
    {
        return $query->where('status', 'pendente');
    }
}
