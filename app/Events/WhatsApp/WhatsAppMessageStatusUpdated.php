<?php

namespace App\Events\WhatsApp;

use App\Models\WhatsApp\WhatsAppMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessageStatusUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public WhatsAppMessage $message,
        public array $statusPayload,
    ) {
    }
}
