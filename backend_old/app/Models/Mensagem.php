<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mensagem extends Model
{
    protected $table = 'mensagens';
    
    public $timestamps = false;
    
    protected $fillable = [
        'conversa_id',
        'message_sid',
        'direction',
        'message_type',
        'content',
        'media_url',
        'transcription',
        'status',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at'
    ];
    
    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime'
    ];
    
    // Relacionamento
    public function conversa()
    {
        return $this->belongsTo(Conversa::class, 'conversa_id');
    }
}
