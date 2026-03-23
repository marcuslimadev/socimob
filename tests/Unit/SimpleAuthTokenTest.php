<?php

namespace Tests\Unit;

use App\Support\SimpleAuthToken;
use Tests\TestCase;

class SimpleAuthTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        config()->set('auth.api_token_ttl', 3600);
    }

    public function test_it_issues_and_decodes_a_signed_token(): void
    {
        $token = SimpleAuthToken::issue(123);

        $claims = SimpleAuthToken::decode($token);

        $this->assertNotNull($claims);
        $this->assertSame(123, $claims['user_id']);
        $this->assertGreaterThan($claims['issued_at'], $claims['expires_at']);
    }

    public function test_it_rejects_tampered_tokens(): void
    {
        $token = SimpleAuthToken::issue(123);

        [$payload, $signature] = explode('.', $token, 2);
        $tamperedPayload = rtrim($payload, 'A') . 'A';

        $this->assertNull(SimpleAuthToken::decode($tamperedPayload . '.' . $signature));
    }

    public function test_it_rejects_legacy_unsigned_tokens(): void
    {
        $legacyToken = base64_encode('123|' . time() . '|legacy-secret');

        $this->assertNull(SimpleAuthToken::decode($legacyToken));
    }
}