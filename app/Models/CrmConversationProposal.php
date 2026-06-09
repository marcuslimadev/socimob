<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmConversationProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'property_id',
        'amount',
        'proposal_type',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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
