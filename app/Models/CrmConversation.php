<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmConversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'property_id',
        'assigned_user_id',
        'channel_id',
        'source',
        'external_identifier_hash',
        'contact_name',
        'contact_phone',
        'status',
        'stage',
        'interest_level',
        'last_message_at',
        'last_summary_at',
        'created_by',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'last_summary_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function channel()
    {
        return $this->belongsTo(CommunicationChannel::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(CrmMessage::class, 'conversation_id');
    }

    public function events()
    {
        return $this->hasMany(CrmConversationEvent::class, 'conversation_id');
    }

    public function tasks()
    {
        return $this->hasMany(CrmConversationTask::class, 'conversation_id');
    }

    public function visits()
    {
        return $this->hasMany(CrmConversationVisit::class, 'conversation_id');
    }

    public function proposals()
    {
        return $this->hasMany(CrmConversationProposal::class, 'conversation_id');
    }
}
