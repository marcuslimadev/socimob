<?php

namespace App\Services;

use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Auth;

class MessageTemplateService
{
    public function getTemplatesForTenant(int $tenantId)
    {
        return MessageTemplate::where("tenant_id", $tenantId)->get();
    }
}
