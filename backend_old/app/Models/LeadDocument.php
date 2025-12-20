<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadDocument extends Model
{
    protected $table = 'lead_documents';

    protected $fillable = [
        'lead_id',
        'conversa_id',
        'mensagem_id',
        'nome',
        'tipo',
        'mime_type',
        'status',
        'arquivo_url',
        'observacoes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function conversa()
    {
        return $this->belongsTo(Conversa::class, 'conversa_id');
    }
}
