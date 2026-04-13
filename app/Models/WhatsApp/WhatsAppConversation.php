<?php

namespace App\Models\WhatsApp;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'whatsapp_phone_number_id',
        'whatsapp_contact_id',
        'external_conversation_id',
        'status',
        'category',
        'conversation_type',
        'started_at',
        'last_message_at',
        'last_inbound_at',
        'last_outbound_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'last_message_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function phoneNumber()
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'whatsapp_phone_number_id');
    }

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_conversation_id');
    }
}
