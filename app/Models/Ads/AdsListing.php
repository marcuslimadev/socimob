<?php

namespace App\Models\Ads;

use App\Models\Traits\BelongsToTenant;
use App\Models\Property;
use Illuminate\Database\Eloquent\Model;

class AdsListing extends Model
{
    use BelongsToTenant;

    protected $table = 'ads_listings';

    protected $fillable = [
        'tenant_id', 'listing_id', 'provider',
        'external_item_id', 'external_catalog_id',
        'publish_status', 'last_sync_at', 'last_error',
        'sync_attempts', 'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'last_sync_at'  => 'datetime',
    ];

    // State machine states
    const STATUS_DRAFT      = 'DRAFT';
    const STATUS_PUBLISHING = 'PUBLISHING';
    const STATUS_ACTIVE     = 'ACTIVE';
    const STATUS_PAUSED     = 'PAUSED';
    const STATUS_ERROR      = 'ERROR';

    public function property(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Property::class, 'listing_id');
    }

    public function markPublishing(): void
    {
        $this->update(['publish_status' => self::STATUS_PUBLISHING, 'last_error' => null]);
    }

    public function markActive(string $externalItemId): void
    {
        $this->update([
            'publish_status'   => self::STATUS_ACTIVE,
            'external_item_id' => $externalItemId,
            'last_sync_at'     => now(),
            'last_error'       => null,
        ]);
    }

    public function markError(string $error): void
    {
        $this->increment('sync_attempts');
        $this->update([
            'publish_status' => self::STATUS_ERROR,
            'last_error'     => $error,
        ]);
    }

    public function markPaused(): void
    {
        $this->update(['publish_status' => self::STATUS_PAUSED]);
    }
}
