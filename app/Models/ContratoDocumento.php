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
        'contrato_compra_venda_id',
        'cobranca_id',
        'tipo',
        'categoria',
        'versao',
        'referencia_documento_id',
        'nome',
        'arquivo_path',
        'assinatura_status',
        'd4sign_uuid',
        'd4sign_key',
        'assinado_em',
        'gerado_por_user_id',
        'gerado_em',
    ];

    protected $appends = ['url_documento', 'status'];

    protected $casts = [
        'versao' => 'integer',
        'referencia_documento_id' => 'integer',
        'assinado_em' => 'datetime',
        'gerado_em' => 'datetime',
    ];

    public function getUrlDocumentoAttribute(): ?string
    {
        if (!$this->arquivo_path) return null;
        return url('storage/' . $this->arquivo_path);
    }

    /** Expõe assinatura_status como "status" para o frontend */
    public function getStatusAttribute(): string
    {
        return $this->assinatura_status ?? 'nao_enviado';
    }

    public function contrato()
    {
        return $this->belongsTo(ContratoLocacao::class, 'contrato_id');
    }

    public function contratoCompraVenda()
    {
        return $this->belongsTo(ContratoCompraVenda::class, 'contrato_compra_venda_id');
    }

    public function cobranca()
    {
        return $this->belongsTo(CobrancaContrato::class, 'cobranca_id');
    }

    public function documentoBase()
    {
        return $this->belongsTo(self::class, 'referencia_documento_id');
    }

    public function versoesAssinadas()
    {
        return $this->hasMany(self::class, 'referencia_documento_id');
    }

    public function isAssinado(): bool
    {
        return $this->assinatura_status === 'assinado';
    }
}
