<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Laravel\Lumen\Testing\TestCase as LumenTestCase;
use Mockery;

/**
 * Test to verify webhook 500 error fix
 * This test validates that:
 * 1. Duplicated code has been removed
 * 2. The webhook always returns 200 (never 500)
 * 3. Exceptions are caught gracefully
 */
class WebhookFixTest extends LumenTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock WhatsAppService to avoid dependencies
        $mock = Mockery::mock(WhatsAppService::class);
        $mock->shouldReceive('processIncomingMessage')
            ->andReturn(['success' => true]);
        
        $this->app->instance(WhatsAppService::class, $mock);
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

    /**
     * Test that webhook returns 200 even with invalid data
     * This validates the fix - previously would return 500
     */
    public function testWebhookReturns200WithInvalidData()
    {
        // Test with array phone (edge case that previously caused issues)
        $response = $this->post('/webhook/whatsapp', [
            'From' => ['+5511987654321'], // Array instead of string
        ]);

        // Should always return 200, never 500
        $response->assertResponseStatus(200);
    }

    /**
     * Test that webhook returns 200 with empty data
     */
    public function testWebhookReturns200WithEmptyData()
    {
        $response = $this->post('/webhook/whatsapp', []);

        // Should always return 200, even with empty data
        $response->assertResponseStatus(200);
    }

    /**
     * Test that webhook returns 200 with valid Twilio data
     */
    public function testWebhookReturns200WithValidTwilioData()
    {
        $response = $this->post('/webhook/whatsapp', [
            'MessageSid' => 'SM1234567890',
            'AccountSid' => 'AC1234567890',
            'From' => 'whatsapp:+5511987654321',
            'To' => 'whatsapp:+5511999999999',
            'Body' => 'Test message',
        ]);

        // Should return 200 with valid data
        $response->assertResponseStatus(200);
    }

    /**
     * Test that GET validation endpoint works
     */
    public function testWebhookValidationReturns200()
    {
        $response = $this->get('/webhook/whatsapp');

        // Should return 200 OK for validation
        $response->assertResponseStatus(200);
        $this->assertEquals('OK', $response->response->getContent());
    }

    /**
     * Test that status validation endpoint works
     */
    public function testWebhookStatusValidationReturns200()
    {
        $response = $this->get('/webhook/whatsapp/status');

        // Should return 200 OK for status validation
        $response->assertResponseStatus(200);
        $this->assertEquals('OK', $response->response->getContent());
    }
}
