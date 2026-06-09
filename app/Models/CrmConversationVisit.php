<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmConversationVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'property_id',
        'scheduled_at',
        'status',
        'notes',
        'participants_json',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'participants_json' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversation()
    {
        return $this->belongsTo(CrmConversation::class, 'conversation_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
