<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'user_id',
        'direction',
        'message_type',
        'body',
        'external_message_id',
        'external_sent_at',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'external_sent_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversation()
    {
        return $this->belongsTo(CrmConversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
