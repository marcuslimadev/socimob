<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantIntegration extends Model
{
    protected $fillable = [
        'tenant_id',
        'provider',
        'channel',
        'integration_version',
        'status',
        'is_active',
        'credentials',
        'settings',
        'webhook_url',
        'webhook_verify_token_hash',
        'connected_at',
        'disconnected_at',
        'last_validated_at',
        'last_error_code',
        'last_error_message',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'last_validated_at' => 'datetime',
    ];

    protected $hidden = [
        'credentials',
        'webhook_verify_token_hash',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function whatsappAccounts()
    {
        return $this->hasMany(\App\Models\WhatsApp\WhatsAppAccount::class, 'tenant_integration_id');
    }
}
