<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsAppService;
use ReflectionClass;
use Tests\TestCase;

class WhatsAppManualOnlyTest extends TestCase
{
    public function test_ai_reply_is_blocked_by_default(): void
    {
        config()->set('services.whatsapp.ai_replies_enabled', false);

        $service = (new ReflectionClass(WhatsAppService::class))->newInstanceWithoutConstructor();
        $conversation = (object) ['id' => 10, 'tenant_id' => 1];

        $result = $service->handleRegularMessage($conversation, 'Olá');

        $this->assertTrue($result['success']);
        $this->assertSame('Mensagem aguardando atendimento manual', $result['message']);
    }
}
