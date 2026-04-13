<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationLog extends Model
{
    protected $table = 'integration_logs';

    protected $fillable = [
        'tenant_id',
        'integration_type',
        'integration_name',
        'channel',
        'direction',
        'operation',
        'endpoint',
        'correlation_id',
        'http_status',
        'success',
        'latency_ms',
        'error_code',
        'error_message',
        'request_payload',
        'response_payload',
    ];

    protected $casts = [
        'success' => 'boolean',
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
