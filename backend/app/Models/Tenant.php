<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'domain',
        'slug',
        'theme',
        'primary_color',
        'secondary_color',
        'logo_url',
        'subscription_status',
        'subscription_plan',
        'subscription_expires_at',
        'subscription_started_at',
        'pagar_me_customer_id',
        'pagar_me_subscription_id',
        'api_key_pagar_me',
        'api_key_apm_imoveis',
        'api_key_neca',
        'api_key_openai',
        'api_token',
        'contact_email',
        'contact_phone',
        'description',
        'is_active',
        'max_users',
        'max_properties',
        'max_leads',
        'metadata',
    ];

    protected $casts = [
        'subscription_expires_at' => 'datetime',
        'subscription_started_at' => 'datetime',
        'is_active' => 'boolean',
        'max_users' => 'integer',
        'max_properties' => 'integer',
        'max_leads' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key_pagar_me',
        'api_key_apm_imoveis',
        'api_key_neca',
        'api_key_openai',
        'api_token',
        'pagar_me_customer_id',
        'pagar_me_subscription_id',
    ];

    /**
     * Relacionamentos
     */
    public function users()
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'tenant_id');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'tenant_id');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'tenant_id');
    }

    public function config()
    {
        return $this->hasOne(TenantConfig::class, 'tenant_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSubscribed($query)
    {
        return $query->where('subscription_status', 'active');
    }

    public function scopeByDomain($query, $domain)
    {
        return $query->where('domain', $domain);
    }

    /**
     * Métodos auxiliares
     */
    public function isSubscribed(): bool
    {
        return $this->subscription_status === 'active' 
            && ($this->subscription_expires_at === null || $this->subscription_expires_at->isFuture());
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function canAddUsers(): bool
    {
        return $this->users()->count() < $this->max_users;
    }

    public function canAddProperties(): bool
    {
        return $this->properties()->count() < $this->max_properties;
    }

    public function canAddLeads(): bool
    {
        return $this->leads()->count() < $this->max_leads;
    }

    public function generateApiToken(): string
    {
        $this->api_token = 'tenant_' . bin2hex(random_bytes(32));
        $this->save();
        return $this->api_token;
    }

    public function getAdminUser()
    {
        return $this->users()
            ->where('role', 'admin')
            ->first();
    }

    public function getAdmins()
    {
        return $this->users()
            ->where('role', 'admin')
            ->get();
    }

    public function getCorrectores()
    {
        return $this->users()
            ->where('role', 'corretor')
            ->get();
    }

    public function getClientes()
    {
        return $this->users()
            ->where('role', 'cliente')
            ->get();
    }

    public function suspendSubscription(string $reason = null): void
    {
        $this->update([
            'subscription_status' => 'suspended',
            'metadata' => array_merge($this->metadata ?? [], [
                'suspension_reason' => $reason,
                'suspended_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function activateSubscription(): void
    {
        $this->update([
            'subscription_status' => 'active',
            'metadata' => array_merge($this->metadata ?? [], [
                'activated_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }
}
