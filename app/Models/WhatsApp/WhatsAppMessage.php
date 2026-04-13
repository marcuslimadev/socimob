<?php

namespace App\Models\WhatsApp;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'public_id',
        'tenant_id',
        'whatsapp_account_id',
        'whatsapp_phone_number_id',
        'whatsapp_conversation_id',
        'whatsapp_contact_id',
        'wamid',
        'direction',
        'message_type',
        'internal_status',
        'meta_message_status',
        'idempotency_key',
        'correlation_id',
        'sender_phone',
        'recipient_phone',
        'body',
        'template_name',
        'template_language',
        'template_payload',
        'payload',
        'context_message_wamid',
        'reply_to_wamid',
        'error_code',
        'error_message',
        'queued_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'received_at',
    ];

    protected $casts = [
        'template_payload' => 'array',
        'payload' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'received_at' => 'datetime',
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

    public function conversation()
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(WhatsAppMessageStatusLog::class, 'whatsapp_message_id');
    }

    public function media()
    {
        return $this->hasMany(WhatsAppMedia::class, 'whatsapp_message_id');
    }
}
