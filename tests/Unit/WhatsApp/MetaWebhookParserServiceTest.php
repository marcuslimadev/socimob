<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\MetaWebhookParserService;
use Tests\TestCase;

class MetaWebhookParserServiceTest extends TestCase
{
    public function test_it_parses_message_and_status_events(): void
    {
        $parser = new MetaWebhookParserService();

        $events = $parser->parse([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'entry-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => 'phone-1',
                        ],
                        'contacts' => [[
                            'profile' => ['name' => 'Maria'],
                            'wa_id' => '5511988887777',
                        ]],
                        'messages' => [[
                            'from' => '5511988887777',
                            'id' => 'wamid-message-1',
                            'timestamp' => '1711111111',
                            'type' => 'text',
                            'text' => ['body' => 'Olá'],
                        ]],
                        'statuses' => [[
                            'id' => 'wamid-message-1',
                            'status' => 'delivered',
                            'timestamp' => '1711111122',
                            'recipient_id' => '5511988887777',
                        ]],
                    ],
                ]],
            ]],
        ]);

        $this->assertCount(2, $events);
        $this->assertSame('message', $events[0]['event_type']);
        $this->assertSame('status', $events[1]['event_type']);
        $this->assertSame('phone-1', $events[0]['phone_number_id']);
    }
}
