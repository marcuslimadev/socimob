<?php

namespace Tests\Unit\Ads;

use App\Services\Ads\TokenEncryptionService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TokenEncryptionTest extends TestCase
{
    private TokenEncryptionService $svc;

    protected function setUp(): void
    {
        putenv('ADS_ENCRYPTION_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        $this->svc = new TokenEncryptionService();
    }

    public function test_encrypt_then_decrypt_roundtrip(): void
    {
        $original = 'EAABsbCS98ZABA...meta_access_token_example';
        $encrypted = $this->svc->encrypt($original);
        $this->assertNotEquals($original, $encrypted);
        $this->assertEquals($original, $this->svc->decrypt($encrypted));
    }

    public function test_encrypted_output_is_base64_string(): void
    {
        $encrypted = $this->svc->encrypt('some_token');
        // Should be a valid base64 string
        $this->assertNotEmpty($encrypted);
        $decoded = base64_decode($encrypted, true);
        $this->assertNotFalse($decoded);
        // IV (12B) + Tag (16B) + at least 1 byte ciphertext = min 29 bytes
        $this->assertGreaterThanOrEqual(29, strlen($decoded));
    }

    public function test_two_encryptions_of_same_value_produce_different_outputs(): void
    {
        $token = 'same_plaintext_token';
        $enc1 = $this->svc->encrypt($token);
        $enc2 = $this->svc->encrypt($token);
        // Each encryption uses a random IV, so outputs should differ
        $this->assertNotEquals($enc1, $enc2);
        // But both decrypt to the same value
        $this->assertEquals($this->svc->decrypt($enc1), $this->svc->decrypt($enc2));
    }

    public function test_decrypt_safe_returns_null_for_null_input(): void
    {
        $this->assertNull($this->svc->decryptSafe(null));
    }

    public function test_decrypt_safe_returns_null_for_empty_string(): void
    {
        $this->assertNull($this->svc->decryptSafe(''));
    }

    public function test_decrypt_safe_returns_null_for_corrupted_data(): void
    {
        $this->assertNull($this->svc->decryptSafe('not_valid_base64_or_too_short'));
    }

    public function test_decrypt_throws_for_corrupted_ciphertext(): void
    {
        $this->expectException(RuntimeException::class);
        $this->svc->decrypt('validbase64buttooshort==');
    }

    public function test_different_keys_cannot_decrypt_each_other(): void
    {
        putenv('ADS_ENCRYPTION_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        $svc1 = new TokenEncryptionService();
        $encrypted = $svc1->encrypt('secret_token');

        putenv('ADS_ENCRYPTION_KEY=ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff');
        $svc2 = new TokenEncryptionService();

        // decryptSafe must not throw, just return null
        $this->assertNull($svc2->decryptSafe($encrypted));
    }
}
