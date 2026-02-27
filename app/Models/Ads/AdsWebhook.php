<?php

namespace App\Models\Ads;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AdsWebhook extends Model
{
    use BelongsToTenant;

    protected $table = 'ads_webhooks';

    protected $fillable = [
        'tenant_id', 'provider',
        'external_subscription_id', 'external_page_id', 'external_form_id',
        'status', 'verify_token_enc',
        'last_verified_at', 'last_event_at', 'metadata_json',
    ];

    protected $hidden = ['verify_token_enc'];

    protected $casts = [
        'metadata_json'   => 'array',
        'last_verified_at'=> 'datetime',
        'last_event_at'   => 'datetime',
    ];

    const STATUS_ACTIVE   = 'ACTIVE';
    const STATUS_INACTIVE = 'INACTIVE';
    const STATUS_ERROR    = 'ERROR';

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function needsReverification(int $hoursThreshold = 24): bool
    {
        if (!$this->last_verified_at) {
            return true;
        }
        return $this->last_verified_at->diffInHours(now()) > $hoursThreshold;
    }
}
