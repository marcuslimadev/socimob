<?php

namespace App\Listeners\WhatsApp;

use App\Events\WhatsApp\WhatsAppMessageStatusUpdated;
use App\Services\WhatsApp\Repositories\IntegrationLogRepository;

class WriteWhatsAppStatusAuditLog
{
    public function __construct(
        protected IntegrationLogRepository $integrationLogRepository,
    ) {
    }

    public function handle(WhatsAppMessageStatusUpdated $event): void
    {
        $this->integrationLogRepository->create([
            'tenant_id' => $event->message->tenant_id,
            'integration_type' => 'whatsapp',
            'integration_name' => 'meta_cloud_api',
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'operation' => 'message_status_updated',
            'endpoint' => 'webhook',
            'correlation_id' => $event->message->correlation_id,
            'http_status' => 200,
            'success' => true,
            'request_payload' => $event->statusPayload,
            'response_payload' => [
                'message_id' => $event->message->id,
                'wamid' => $event->message->wamid,
                'meta_message_status' => $event->message->meta_message_status,
                'internal_status' => $event->message->internal_status,
            ],
        ]);
    }
}
