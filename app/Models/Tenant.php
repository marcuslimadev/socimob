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
        'favicon_url',
        'slogan',
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
        'api_url_externa',
        'api_token_externa',
        'api_token',
        'contact_email',
        'contact_phone',
        'description',
        'is_active',
        'max_users',
        'max_properties',
        'max_leads',
        'metadata',
        // Twilio
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_whatsapp_from',
        'twilio_template_welcome_sid',
        // OpenAI
        'openai_api_key',
        'openai_model',
        'ai_assistant_name',
        // Email
        'mail_driver',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
        // Additional
        'razao_social',
        'cnpj',
        'endereco',
        'mascot_url',
        'watermark_url',
        'creci',
        'about_text',
        'services',
        'social_links',
        'neighborhoods',
        'office_hours',
    ];

    protected $casts = [
        'subscription_expires_at' => 'datetime',
        'subscription_started_at' => 'datetime',
        'is_active' => 'boolean',
        'max_users' => 'integer',
        'max_properties' => 'integer',
        'max_leads' => 'integer',
        'metadata' => 'array',
        'services' => 'array',
        'social_links' => 'array',
        'neighborhoods' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key_pagar_me',
        'api_key_apm_imoveis',
        'api_key_neca',
        'api_key_openai',
        'api_url_externa',
        'api_token_externa',
        'api_token',
        'pagar_me_customer_id',
        'pagar_me_subscription_id',
        'twilio_auth_token',
        'openai_api_key',
        'mail_password',
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

    /**
     * Obtém valor de configuração com fallback para .env
     * Prioridade: 1. Banco de dados, 2. .env específico do tenant, 3. .env global, 4. default
     */
    public function getIntegrationValue(string $key, $default = null)
    {
        // 1. Verificar se existe no banco de dados (prioridade)
        if (array_key_exists($key, $this->getAttributes()) && $this->{$key} !== null && $this->{$key} !== '') {
            return $this->{$key};
        }

        // 2. Verificar metadata
        if (is_array($this->metadata) && isset($this->metadata[$key])) {
            return $this->metadata[$key];
        }

        // 3. Verificar .env específico do tenant por aliases do slug e do nome.
        $tenantPrefixes = collect([
            $this->slug,
            $this->name,
        ])
            ->filter()
            ->flatMap(function (string $value) {
                $normalized = preg_replace('/[^A-Za-z0-9]+/', '_', $value);
                $normalized = trim((string) $normalized, '_');

                if ($normalized === '') {
                    return [];
                }

                $parts = array_values(array_filter(explode('_', strtoupper($normalized))));

                return array_values(array_unique(array_filter([
                    implode('_', $parts),
                    $parts[0] ?? null,
                ])));
            })
            ->unique()
            ->values();

        $envKeyAliases = collect([
            strtoupper($key),
            str_starts_with($key, 'nfeio_') ? 'NFE_IO_' . strtoupper(substr($key, strlen('nfeio_'))) : null,
        ])
            ->filter()
            ->unique()
            ->values();

        foreach ($tenantPrefixes as $tenantPrefix) {
            foreach ($envKeyAliases as $envKeyAlias) {
                $valueFromTenantEnv = env($tenantPrefix . '_' . $envKeyAlias);

                if ($valueFromTenantEnv !== null && $valueFromTenantEnv !== '') {
                    return $valueFromTenantEnv;
                }
            }
        }

        // 4. Verificar .env global (ex: TWILIO_ACCOUNT_SID, NFE_IO_API_KEY)
        foreach ($envKeyAliases as $envKeyAlias) {
            $valueFromGlobalEnv = env($envKeyAlias);

            if ($valueFromGlobalEnv !== null && $valueFromGlobalEnv !== '') {
                return $valueFromGlobalEnv;
            }
        }

        // 5. Retornar default
        return $default;
    }

    /**
     * Atalhos para configurações comuns
     */
    public function getTwilioAccountSid()
    {
        return $this->getIntegrationValue('twilio_account_sid');
    }

    public function getTwilioAuthToken()
    {
        return $this->getIntegrationValue('twilio_auth_token');
    }

    public function getTwilioWhatsappFrom()
    {
        return $this->getIntegrationValue('twilio_whatsapp_from');
    }

    public function getOpenAiApiKey()
    {
        return $this->getIntegrationValue('openai_api_key') 
            ?? $this->getIntegrationValue('api_key_openai');
    }

    public function getOpenAiModel()
    {
        return $this->getIntegrationValue('openai_model', 'gpt-4o-mini');
    }

    public function getAiAssistantName()
    {
        return $this->getIntegrationValue('ai_assistant_name', 'Teresa');
    }

    public function getCompanyName()
    {
        return $this->name ?: ($this->razao_social ?: 'Imobiliária');
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

    /**
     * Retorna configuração pública do tenant para o portal
     *
     * @return array
     */
    public function getPublicConfig(): array
    {
        return [
            'name' => $this->name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'slogan' => $this->slogan ?? 'Encontre o Imóvel dos Seus Sonhos',
            'primary_color' => $this->primary_color ?? '#1e293b',
            'secondary_color' => $this->secondary_color ?? '#3b82f6',
            'logo_url' => $this->logo_url ?? '/images/logo.png',
            'favicon_url' => $this->favicon_url ?? '/favicon.ico',
            'mascot_url' => $this->mascot_url,
            'creci' => $this->creci,
            'about_text' => $this->about_text,
            'services' => $this->services,
            'social_links' => $this->social_links,
            'endereco' => $this->endereco,
            'office_hours' => $this->office_hours,
        ];
    }
}
