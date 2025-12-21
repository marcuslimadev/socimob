<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
