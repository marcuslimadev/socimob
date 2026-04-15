<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\Support\MetaWebhookSignatureValidator;
use Tests\TestCase;

class MetaWebhookSignatureValidatorTest extends TestCase
{
    public function test_it_validates_a_correct_signature(): void
    {
        $payload = '{"hello":"world"}';
        $secret = 'top-secret';
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        $validator = new MetaWebhookSignatureValidator();

        $this->assertTrue($validator->isValid($payload, $signature, $secret));
        $this->assertFalse($validator->isValid($payload, $signature, 'wrong-secret'));
    }
}
