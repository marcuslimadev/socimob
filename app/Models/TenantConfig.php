<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantConfig extends Model
{
    protected $table = 'tenant_configs';

    protected $fillable = [
        'tenant_id',
        'api_key_pagar_me',
        'api_key_apm_imoveis',
        'api_key_neca',
        'api_key_openai',
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_whatsapp_from',
        'primary_color',
        'secondary_color',
        'accent_color',
        'logo_url',
        'favicon_url',
        'portal_finalidades',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_from_email',
        'smtp_from_name',
        'notify_new_leads',
        'notify_new_properties',
        'notify_new_messages',
        'notification_email',
        'max_images_per_property',
        'max_properties',
        'require_approval_for_properties',
        'max_leads',
        'auto_assign_leads',
        'metadata',
    ];

    protected $casts = [
        'smtp_port' => 'integer',
        'notify_new_leads' => 'boolean',
        'notify_new_properties' => 'boolean',
        'notify_new_messages' => 'boolean',
        'max_images_per_property' => 'integer',
        'max_properties' => 'integer',
        'require_approval_for_properties' => 'boolean',
        'max_leads' => 'integer',
        'auto_assign_leads' => 'boolean',
        'portal_finalidades' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key_pagar_me',
        'api_key_apm_imoveis',
        'api_key_neca',
        'api_key_openai',
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_whatsapp_from',
        'smtp_password',
    ];

    /**
     * Relacionamentos
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Métodos auxiliares
     */
    public function getSmtpConfig(): array
    {
        // Tentar buscar de variáveis de ambiente primeiro
        $tenantId = $this->tenant_id;
        
        return [
            'host' => env("TENANT_{$tenantId}_SMTP_HOST", $this->smtp_host),
            'port' => env("TENANT_{$tenantId}_SMTP_PORT", $this->smtp_port),
            'username' => env("TENANT_{$tenantId}_SMTP_USERNAME", $this->smtp_username),
            'password' => env("TENANT_{$tenantId}_SMTP_PASSWORD", $this->smtp_password),
            'from' => [
                'address' => env("TENANT_{$tenantId}_SMTP_FROM_EMAIL", $this->smtp_from_email),
                'name' => env("TENANT_{$tenantId}_SMTP_FROM_NAME", $this->smtp_from_name),
            ],
        ];
    }

    public function setSmtpConfig(array $config): void
    {
        $this->update([
            'smtp_host' => $config['host'] ?? null,
            'smtp_port' => $config['port'] ?? 587,
            'smtp_username' => $config['username'] ?? null,
            'smtp_password' => $config['password'] ?? null,
            'smtp_from_email' => $config['from']['address'] ?? null,
            'smtp_from_name' => $config['from']['name'] ?? null,
        ]);
    }

    public function getApiKeys(): array
    {
        // Tentar buscar de variáveis de ambiente primeiro
        $tenantId = $this->tenant_id;
        
        return [
            'pagar_me' => env("TENANT_{$tenantId}_PAGAR_ME_KEY", $this->api_key_pagar_me),
            'apm_imoveis' => env("TENANT_{$tenantId}_APM_IMOVEIS_KEY", $this->api_key_apm_imoveis),
            'neca' => env("TENANT_{$tenantId}_NECA_KEY", $this->api_key_neca),
            'openai' => env("TENANT_{$tenantId}_OPENAI_KEY", $this->api_key_openai),
            'twilio_account_sid' => env("TENANT_{$tenantId}_TWILIO_ACCOUNT_SID", $this->twilio_account_sid),
            'twilio_auth_token' => env("TENANT_{$tenantId}_TWILIO_AUTH_TOKEN", $this->twilio_auth_token),
            'twilio_whatsapp_from' => env("TENANT_{$tenantId}_TWILIO_WHATSAPP_FROM", $this->twilio_whatsapp_from),
        ];
    }

    public function setApiKey(string $service, string $key): void
    {
        $this->update([
            'api_key_' . $service => $key,
        ]);
    }

    public function getThemeColors(): array
    {
        return [
            'primary' => $this->primary_color ?? '#000000',
            'secondary' => $this->secondary_color ?? '#FFFFFF',
            'accent' => $this->accent_color ?? '#FF6B6B',
        ];
    }

    public function setThemeColors(array $colors): void
    {
        $this->update([
            'primary_color' => $colors['primary'] ?? '#000000',
            'secondary_color' => $colors['secondary'] ?? '#FFFFFF',
            'accent_color' => $colors['accent'] ?? '#FF6B6B',
        ]);
    }

    public function getNotificationSettings(): array
    {
        return [
            'new_leads' => $this->notify_new_leads,
            'new_properties' => $this->notify_new_properties,
            'new_messages' => $this->notify_new_messages,
            'email' => $this->notification_email,
        ];
    }

    public function setNotificationSettings(array $settings): void
    {
        $this->update([
            'notify_new_leads' => $settings['new_leads'] ?? true,
            'notify_new_properties' => $settings['new_properties'] ?? true,
            'notify_new_messages' => $settings['new_messages'] ?? true,
            'notification_email' => $settings['email'] ?? null,
        ]);
    }

    public function getLimits(): array
    {
        return [
            'images_per_property' => $this->max_images_per_property,
            'properties' => $this->max_properties,
            'leads' => $this->max_leads,
        ];
    }

    public function setLimits(array $limits): void
    {
        $this->update([
            'max_images_per_property' => $limits['images_per_property'] ?? 20,
            'max_properties' => $limits['properties'] ?? 1000,
            'max_leads' => $limits['leads'] ?? 5000,
        ]);
    }
}
