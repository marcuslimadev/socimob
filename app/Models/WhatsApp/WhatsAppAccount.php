<?php

namespace App\Models\WhatsApp;

use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Database\Eloquent\Model;

class WhatsAppAccount extends Model
{
    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'tenant_id',
        'tenant_integration_id',
        'meta_business_account_id',
        'waba_id',
        'app_id',
        'app_secret',
        'access_token',
        'system_user_id',
        'status',
        'display_name_status',
        'quality_rating',
        'metadata',
        'connected_at',
        'last_token_validated_at',
    ];

    protected $casts = [
        'app_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'metadata' => 'array',
        'connected_at' => 'datetime',
        'last_token_validated_at' => 'datetime',
    ];

    protected $hidden = [
        'app_secret',
        'access_token',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function tenantIntegration()
    {
        return $this->belongsTo(TenantIntegration::class, 'tenant_integration_id');
    }

    public function phoneNumbers()
    {
        return $this->hasMany(WhatsAppPhoneNumber::class, 'whatsapp_account_id');
    }

    public function templates()
    {
        return $this->hasMany(WhatsAppTemplate::class, 'whatsapp_account_id');
    }
}
