<?php

namespace App\Models\WhatsApp;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessageStatusLog extends Model
{
    protected $table = 'whatsapp_message_status_logs';

    protected $fillable = [
        'tenant_id',
        'whatsapp_message_id',
        'wamid',
        'status',
        'status_at',
        'recipient_id',
        'billable',
        'pricing_category',
        'errors',
        'raw_payload',
    ];

    protected $casts = [
        'billable' => 'boolean',
        'errors' => 'array',
        'raw_payload' => 'array',
        'status_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function message()
    {
        return $this->belongsTo(WhatsAppMessage::class, 'whatsapp_message_id');
    }
}
