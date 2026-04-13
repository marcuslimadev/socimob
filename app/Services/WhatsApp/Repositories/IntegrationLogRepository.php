<?php

namespace App\Services\WhatsApp\Repositories;

use App\Models\IntegrationLog;

class IntegrationLogRepository
{
    public function create(array $attributes): IntegrationLog
    {
        return IntegrationLog::query()->create($attributes);
    }
}
