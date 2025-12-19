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
        'primary_color',
        'secondary_color',
        'accent_color',
        'logo_url',
        'favicon_url',
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
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key_pagar_me',
        'api_key_apm_imoveis',
        'api_key_neca',
        'api_key_openai',
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
        return [
            'host' => $this->smtp_host,
            'port' => $this->smtp_port,
            'username' => $this->smtp_username,
            'password' => $this->smtp_password,
            'from' => [
                'address' => $this->smtp_from_email,
                'name' => $this->smtp_from_name,
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
        return [
            'pagar_me' => $this->api_key_pagar_me,
            'apm_imoveis' => $this->api_key_apm_imoveis,
            'neca' => $this->api_key_neca,
            'openai' => $this->api_key_openai,
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
