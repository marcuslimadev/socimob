<?php

namespace App\Models\Ads;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AdsCampaign extends Model
{
    use BelongsToTenant;

    protected $table = 'ads_campaigns';

    protected $fillable = [
        'tenant_id', 'provider',
        'external_campaign_id', 'external_adset_id',
        'objective', 'status',
        'budget_daily_cents', 'region',
        'geo_lat', 'geo_lng', 'geo_radius_km',
        'metadata_json', 'last_reconciled_at',
    ];

    protected $casts = [
        'metadata_json'      => 'array',
        'geo_lat'            => 'double',
        'geo_lng'            => 'double',
        'last_reconciled_at' => 'datetime',
    ];

    const STATUS_DRAFT  = 'DRAFT';
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_PAUSED = 'PAUSED';
    const STATUS_ERROR  = 'ERROR';

    const OBJECTIVE_LEADS   = 'LEAD_GENERATION';
    const OBJECTIVE_CATALOG = 'CATALOG_SALES';

    public function getBudgetInReais(): float
    {
        return $this->budget_daily_cents / 100;
    }
}
