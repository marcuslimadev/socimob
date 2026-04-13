<?php

namespace App\Models\WhatsApp;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppContact extends Model
{
    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'tenant_id',
        'whatsapp_phone_number_id',
        'wa_id',
        'phone_e164',
        'profile_name',
        'contact_name',
        'metadata',
        'opted_in_at',
        'opted_out_at',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'opted_in_at' => 'datetime',
        'opted_out_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function phoneNumber()
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'whatsapp_phone_number_id');
    }

    public function conversations()
    {
        return $this->hasMany(WhatsAppConversation::class, 'whatsapp_contact_id');
    }
}
