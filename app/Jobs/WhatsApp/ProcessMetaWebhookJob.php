<?php

namespace App\Jobs\WhatsApp;

use App\Services\WhatsApp\WhatsAppWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMetaWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;

    public function __construct(
        public int $webhookEventId,
    ) {
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(WhatsAppWebhookService $webhookService): void
    {
        $webhookService->process($this->webhookEventId);
    }
}
