<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialTransaction extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'commission_invoice_id',
        'user_id',
        'tipo',
        'status',
        'vencimento',
        'data_pagamento',
        'valor',
        'forma_pagamento',
        'referencia_externa',
        'descricao',
        'metadata',
    ];

    protected $casts = [
        'vencimento' => 'date',
        'data_pagamento' => 'date',
        'valor' => 'float',
        'metadata' => 'array',
    ];

    public function commissionInvoice()
    {
        return $this->belongsTo(CommissionInvoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
