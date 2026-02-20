<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentoFiscal extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'documentos_fiscais';

    protected $fillable = [
        'tenant_id',
        'cobranca_id',
        'locador_pessoa_id',
        'locatario_pessoa_id',
        'tipo',
        'status',
        'numero',
        'serie',
        'codigo_verificacao',
        'emitida_em',
        'valor_servico',
        'valor_impostos',
        'service_item_code',
        'city_service_code',
        'url_pdf',
        'url_xml',
        'payload',
        'retorno',
    ];

    protected $casts = [
        'emitida_em' => 'datetime',
        'valor_servico' => 'float',
        'valor_impostos' => 'float',
        'payload' => 'array',
        'retorno' => 'array',
    ];

    public function cobranca()
    {
        return $this->belongsTo(CobrancaContrato::class, 'cobranca_id');
    }
}
