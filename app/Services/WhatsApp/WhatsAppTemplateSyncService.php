<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsApp\WhatsAppPhoneNumber;
use App\Models\WhatsApp\WhatsAppTemplate;

class WhatsAppTemplateSyncService
{
    public function __construct(
        protected MetaCloudApiService $metaCloudApiService,
    ) {
    }

    public function sync(WhatsAppPhoneNumber $phoneNumber, string $correlationId): array
    {
        $response = $this->metaCloudApiService->listTemplates($phoneNumber->account, $correlationId);
        $count = 0;

        foreach ($response['data'] ?? [] as $template) {
            WhatsAppTemplate::query()->updateOrCreate(
                [
                    'tenant_id' => $phoneNumber->tenant_id,
                    'whatsapp_account_id' => $phoneNumber->whatsapp_account_id,
                    'name' => $template['name'],
                    'language' => $template['language'] ?? 'pt_BR',
                ],
                [
                    'external_template_id' => $template['id'] ?? null,
                    'category' => $template['category'] ?? null,
                    'status' => $template['status'] ?? null,
                    'quality_score' => $template['quality_score']['score'] ?? null,
                    'parameter_format' => $template['parameter_format'] ?? null,
                    'components' => $template['components'] ?? [],
                    'metadata' => $template,
                    'last_synced_at' => now(),
                    'rejected_reason' => $template['rejected_reason'] ?? null,
                ]
            );

            $count++;
        }

        return [
            'count' => $count,
            'templates' => $response['data'] ?? [],
        ];
    }
}
