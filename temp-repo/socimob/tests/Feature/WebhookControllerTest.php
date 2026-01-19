<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Laravel\Lumen\Testing\TestCase as LumenTestCase;
use Mockery;

class WebhookControllerTest extends LumenTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(WhatsAppService::class, $this->mockWhatsAppService());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function createApplication()
    {
        return require __DIR__ . '/../../bootstrap/app.php';
    }

    public function testReceiveHandlesArrayPhoneWithoutError()
    {
        $response = $this->post('/webhook/whatsapp', [
            'From' => ['+5511987654321'],
        ]);

        $response->assertResponseStatus(200);
    }

    private function mockWhatsAppService()
    {
        $mock = Mockery::mock(WhatsAppService::class);
        $mock->shouldReceive('processIncomingMessage')
            ->once()
            ->with(Mockery::on(function (array $payload) {
                return array_key_exists('from', $payload)
                    && $payload['from'] === null
                    && ($payload['source'] ?? null) === 'unknown';
            }))
            ->andReturn(['success' => false]);

        return $mock;
    }
}
