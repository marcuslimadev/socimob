<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $lead_id
 * @property int|null $conversa_id
 * @property int|null $mensagem_id
 * @property string|null $nome
 * @property string|null $tipo
 * @property string|null $mime_type
 * @property string|null $arquivo_url
 * @property string|null $status
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class LeadDocument extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'conversa_id',
        'mensagem_id',
        'nome',
        'tipo',
        'mime_type',
        'arquivo_url',
        'status',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function conversa()
    {
        return $this->belongsTo(Conversa::class);
    }

    public function mensagem()
    {
        return $this->belongsTo(Mensagem::class);
    }
}
