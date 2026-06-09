<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmConversationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'user_id',
        'event_type',
        'title',
        'description',
        'payload_json',
        'source',
    ];

    protected $casts = [
        'payload_json' => 'array',
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
