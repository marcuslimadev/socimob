<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mensagem extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'mensagens';

    protected $fillable = [
        'tenant_id',
        'conversa_id',
        'user_id',
        'message_sid',
        'direction',
        'message_type',
        'content',
        'media_url',
        'transcription',
        'status',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function conversa()
    {
        return $this->belongsTo(Conversa::class);
    }
}
