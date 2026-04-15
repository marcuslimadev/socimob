<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\WhatsApp\ProcessMetaWebhookJob;
use App\Models\WhatsApp\WhatsAppWebhookEvent;
use Illuminate\Support\Facades\Queue;

class MetaWebhookControllerTest extends WhatsAppFeatureTestCase
{
    public function test_it_verifies_the_meta_webhook(): void
    {
        config()->set('whatsapp.graph.webhook_verify_token', 'verify-token-test');

        $response = $this->get('/api/whatsapp/webhook/meta?hub_mode=subscribe&hub_verify_token=verify-token-test&hub_challenge=12345');

        $response->assertOk();
        $this->assertSame('12345', $response->getContent());
    }

    public function test_it_receives_and_deduplicates_a_signed_webhook(): void
    {
        Queue::fake();
        $tenant = $this->createTenant();
        $connection = $this->createConnection($tenant);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-entry-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '+55 11 99999-9999',
                            'phone_number_id' => $connection['phoneNumber']->phone_number_id,
                        ],
                        'contacts' => [[
                            'profile' => ['name' => 'Maria'],
                            'wa_id' => '5511988887777',
                        ]],
                        'messages' => [[
                            'from' => '5511988887777',
                            'id' => 'wamid.HBgLMQ==',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'Olá'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $rawPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = 'sha256=' . hash_hmac('sha256', $rawPayload, $connection['account']->app_secret);

        $response = $this->call(
            'POST',
            '/api/whatsapp/webhook/meta',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $rawPayload
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('accepted', 1)
            ->assertJsonPath('duplicates', 0)
            ->assertJsonPath('signature_valid', true);

        $this->assertDatabaseCount('whatsapp_webhook_events', 1);
        Queue::assertPushed(ProcessMetaWebhookJob::class, 1);

        $duplicateResponse = $this->call(
            'POST',
            '/api/whatsapp/webhook/meta',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $rawPayload
        );

        $duplicateResponse->assertOk()
            ->assertJsonPath('accepted', 0)
            ->assertJsonPath('duplicates', 1);

        $this->assertSame(1, WhatsAppWebhookEvent::query()->count());
    }
}
