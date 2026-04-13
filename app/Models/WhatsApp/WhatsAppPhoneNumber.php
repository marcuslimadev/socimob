<?php

namespace App\Models\WhatsApp;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppPhoneNumber extends Model
{
    protected $table = 'whatsapp_phone_numbers';

    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'phone_number_id',
        'display_phone_number',
        'e164_phone_number',
        'verified_name',
        'status',
        'code_verification_status',
        'quality_rating',
        'messaging_limit_tier',
        'current_provider',
        'migrated_from_provider',
        'is_default',
        'is_active',
        'last_health_check_at',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'last_health_check_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function contacts()
    {
        return $this->hasMany(WhatsAppContact::class, 'whatsapp_phone_number_id');
    }
}
