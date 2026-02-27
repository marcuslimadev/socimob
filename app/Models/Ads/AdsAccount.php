<?php

namespace App\Models\Ads;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AdsAccount extends Model
{
    use BelongsToTenant;

    protected $table = 'ads_accounts';

    protected $fillable = [
        'tenant_id', 'provider', 'external_account_id',
        'name', 'currency', 'timezone', 'is_active', 'metadata_json',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'metadata_json' => 'array',
    ];
}
