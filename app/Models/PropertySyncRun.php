<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertySyncRun extends Model
{
    protected $table = 'property_sync_runs';

    protected $fillable = [
        'tenant_id',
        'triggered_by_user_id',
        'trigger_type',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'result_payload',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'result_payload' => 'array',
    ];
}
