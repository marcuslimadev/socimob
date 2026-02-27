<?php

namespace App\Models\Ads;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AdsConnection extends Model
{
    use BelongsToTenant;

    protected $table = 'ads_connections';

    protected $fillable = [
        'tenant_id', 'provider', 'status',
        'token_enc', 'refresh_token_enc', 'scopes',
        'expires_at', 'external_user_id', 'external_business_id',
        'metadata_json', 'last_refresh_at', 'disconnected_at',
    ];

    protected $hidden = ['token_enc', 'refresh_token_enc'];

    protected $casts = [
        'scopes'        => 'array',
        'metadata_json' => 'array',
        'expires_at'    => 'datetime',
        'last_refresh_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    // Status constants
    const STATUS_DRAFT     = 'DRAFT';
    const STATUS_CONNECTED = 'CONNECTED';
    const STATUS_READY     = 'READY';
    const STATUS_ERROR     = 'ERROR';

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED
            || $this->status === self::STATUS_READY;
    }

    public function account(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AdsAccount::class, 'tenant_id', 'tenant_id')
            ->where('provider', $this->provider);
    }
}
