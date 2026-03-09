<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ContratoDocumento extends Model
{
    use BelongsToTenant;

    protected $table = 'contratos_documentos';

    protected $fillable = [
        'tenant_id',
        'contrato_id',
        'cobranca_id',
        'tipo',
        'nome',
        'arquivo_path',
        'assinatura_status',
        'd4sign_uuid',
        'd4sign_key',
        'assinado_em',
        'gerado_por_user_id',
        'gerado_em',
    ];

    protected $casts = [
        'assinado_em' => 'datetime',
        'gerado_em' => 'datetime',
    ];

    public function contrato()
    {
        return $this->belongsTo(ContratoLocacao::class, 'contrato_id');
    }

    public function cobranca()
    {
        return $this->belongsTo(CobrancaContrato::class, 'cobranca_id');
    }

    public function isAssinado(): bool
    {
        return $this->assinatura_status === 'assinado';
    }
}
