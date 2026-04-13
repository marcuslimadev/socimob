<?php

namespace App\Models\WhatsApp;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppWebhookEvent extends Model
{
    protected $table = 'whatsapp_webhook_events';

    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'whatsapp_phone_number_id',
        'object_type',
        'entry_id',
        'change_field',
        'event_type',
        'delivery_key',
        'payload_hash',
        'signature_valid',
        'attempt_count',
        'correlation_id',
        'headers',
        'event_payload',
        'raw_payload',
        'queued_at',
        'processed_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'headers' => 'array',
        'event_payload' => 'array',
        'queued_at' => 'datetime',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
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
}
