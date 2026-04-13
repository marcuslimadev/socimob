<?php

namespace App\Models\WhatsApp;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'external_template_id',
        'name',
        'language',
        'category',
        'status',
        'quality_score',
        'parameter_format',
        'components',
        'metadata',
        'last_synced_at',
        'rejected_reason',
    ];

    protected $casts = [
        'components' => 'array',
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }
}
