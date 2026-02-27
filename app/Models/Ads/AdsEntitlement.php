<?php

namespace App\Models\Ads;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AdsEntitlement extends Model
{
    use BelongsToTenant;

    protected $table = 'ads_entitlements';

    protected $fillable = [
        'tenant_id', 'plan_code',
        'providers_allowed', 'max_listings_per_day',
        'max_budget_daily_cents', 'regions_allowed',
        'remarketing_enabled', 'capi_enabled',
        'multi_account_enabled', 'valid_from',
        'valid_until', 'is_active',
    ];

    protected $casts = [
        'providers_allowed'    => 'array',
        'regions_allowed'      => 'array',
        'remarketing_enabled'  => 'boolean',
        'capi_enabled'         => 'boolean',
        'multi_account_enabled'=> 'boolean',
        'is_active'            => 'boolean',
        'valid_from'           => 'datetime',
        'valid_until'          => 'datetime',
    ];

    const PLAN_BASIC      = 'ADS_BASIC';
    const PLAN_PRO        = 'ADS_PRO';
    const PLAN_ENTERPRISE = 'ADS_ENTERPRISE';

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        $now = now();
        if ($this->valid_from && $this->valid_from->isFuture()) {
            return false;
        }
        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }
        return true;
    }

    public function allowsProvider(string $provider): bool
    {
        $allowed = $this->providers_allowed ?? [];
        return in_array($provider, $allowed);
    }

    public function getMaxBudgetInReais(): float
    {
        return $this->max_budget_daily_cents / 100;
    }
}
