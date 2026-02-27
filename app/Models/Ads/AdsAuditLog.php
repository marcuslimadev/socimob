<?php

namespace App\Models\Ads;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AdsAuditLog extends Model
{
    use BelongsToTenant;

    protected $table = 'ads_audit_logs';

    // Audit logs nunca devem ser atualizados após criação
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'provider',
        'entity_type', 'entity_id',
        'action', 'status', 'request_id',
        'message', 'payload_json_sanitized',
        'http_status', 'duration_ms',
    ];

    protected $casts = [
        'payload_json_sanitized' => 'array',
    ];

    // Actions
    const ACTION_PUBLISH_REQUESTED  = 'PUBLISH_REQUESTED';
    const ACTION_UNPUBLISH_REQUESTED= 'UNPUBLISH_REQUESTED';
    const ACTION_CATALOG_UPSERT     = 'CATALOG_UPSERT';
    const ACTION_CATALOG_DELETE     = 'CATALOG_DELETE';
    const ACTION_CAMPAIGN_ENSURE    = 'CAMPAIGN_ENSURE';
    const ACTION_WEBHOOK_SUBSCRIBE  = 'WEBHOOK_SUBSCRIBE';
    const ACTION_WEBHOOK_VERIFY     = 'WEBHOOK_VERIFY';
    const ACTION_LEAD_RECEIVED      = 'LEAD_RECEIVED';
    const ACTION_LEAD_INGESTED      = 'LEAD_INGESTED';
    const ACTION_LEAD_DUPLICATE     = 'LEAD_DUPLICATE';
    const ACTION_TOKEN_REFRESH      = 'TOKEN_REFRESH';
    const ACTION_RECONCILE          = 'RECONCILE';
    const ACTION_OAUTH_START        = 'OAUTH_START';
    const ACTION_OAUTH_CALLBACK     = 'OAUTH_CALLBACK';

    // Statuses
    const STATUS_SUCCESS = 'SUCCESS';
    const STATUS_ERROR   = 'ERROR';
    const STATUS_SKIPPED = 'SKIPPED';

    // Entity types
    const ENTITY_CONNECTION = 'connection';
    const ENTITY_LISTING    = 'listing';
    const ENTITY_CAMPAIGN   = 'campaign';
    const ENTITY_WEBHOOK    = 'webhook';
    const ENTITY_LEAD       = 'lead';

    /**
     * Helper estático para logar sem instanciar manualmente.
     */
    public static function log(
        int     $tenantId,
        string  $action,
        string  $status,
        array   $context = []
    ): self {
        return static::create([
            'tenant_id'               => $tenantId,
            'provider'                => $context['provider'] ?? null,
            'entity_type'             => $context['entity_type'] ?? null,
            'entity_id'               => $context['entity_id'] ?? null,
            'action'                  => $action,
            'status'                  => $status,
            'request_id'              => $context['request_id'] ?? (string)\Illuminate\Support\Str::uuid(),
            'message'                 => $context['message'] ?? null,
            'payload_json_sanitized'  => $context['payload'] ?? null,
            'http_status'             => $context['http_status'] ?? null,
            'duration_ms'             => $context['duration_ms'] ?? null,
        ]);
    }
}
