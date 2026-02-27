<?php

namespace App\Models\Ads;

use App\Models\Traits\BelongsToTenant;
use App\Models\Lead;
use App\Models\Pessoa;
use App\Models\Property;
use Illuminate\Database\Eloquent\Model;

class AdsLead extends Model
{
    use BelongsToTenant;

    protected $table = 'ads_leads';

    protected $fillable = [
        'tenant_id', 'provider', 'external_lead_id',
        'listing_id', 'contact_id', 'crm_lead_id',
        'external_campaign_id', 'external_adset_id',
        'external_ad_id', 'external_form_id', 'gclid',
        'raw_payload_json', 'normalized_json',
        'is_duplicate', 'received_at',
    ];

    protected $casts = [
        'raw_payload_json' => 'array',
        'normalized_json'  => 'array',
        'is_duplicate'     => 'boolean',
        'received_at'      => 'datetime',
    ];

    public function property(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Property::class, 'listing_id');
    }

    public function contact(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'contact_id');
    }

    public function crmLead(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Lead::class, 'crm_lead_id');
    }

    public function getNomalizadoNome(): ?string
    {
        return $this->normalized_json['nome'] ?? null;
    }

    public function getNormalizadoEmail(): ?string
    {
        return $this->normalized_json['email'] ?? null;
    }

    public function getNormalizadoTelefone(): ?string
    {
        return $this->normalized_json['telefone'] ?? null;
    }
}
