<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsShortLink extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'sms_short_links';

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'code',
        'whatsapp_number',
        'message_preview',
        'sms_message_sid',
        'used_at',
        'used_message_sid',
        'used_mensagem_id',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];
}
