<?php

namespace App\Models\Ads;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AdsCatalog extends Model
{
    use BelongsToTenant;

    protected $table = 'ads_catalogs';

    protected $fillable = [
        'tenant_id', 'provider', 'external_catalog_id',
        'name', 'status', 'items_count', 'last_sync_at', 'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'last_sync_at'  => 'datetime',
    ];

    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_PAUSED = 'PAUSED';
    const STATUS_ERROR  = 'ERROR';
}
