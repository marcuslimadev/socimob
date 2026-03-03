<?php

namespace App\Services\Ads;

use RuntimeException;

/**
 * Cifra/decifra tokens em repouso usando AES-256-GCM.
 * A chave é derivada de ADS_ENCRYPTION_KEY no .env.
 * NUNCA salvar o valor decifrado em logs ou banco.
 */
class TokenEncryptionService
{
    private string $key;
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LEN = 16;

    public function __construct()
    {
        $rawKey = env('ADS_ENCRYPTION_KEY', 'default-key-for-migration');
        // Deriva uma chave de 32 bytes a partir do valor fornecido
        $this->key = hash('sha256', $rawKey, true);
    }

    /**
     * Cifra um texto puro e retorna base64(IV + Tag + Ciphertext).
     */
    public function encrypt(string $plaintext): string
    {
        $iv  = random_bytes(12); // 96-bit IV recomendado para GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Falha ao cifrar token: ' . openssl_error_string());
        }

        // Empacota: IV (12B) + Tag (16B) + Ciphertext
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decifra um valor previamente cifrado com encrypt().
     */
    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('Token criptografado inválido ou corrompido');
        }

        $iv         = substr($raw, 0, 12);
        $tag        = substr($raw, 12, self::TAG_LEN);
        $ciphertext = substr($raw, 12 + self::TAG_LEN);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Falha ao decifrar token — chave incorreta ou dado adulterado');
        }

        return $plaintext;
    }

    /**
     * Retorna null em vez de lançar exceção (útil para leitura silenciosa).
     */
    public function decryptSafe(?string $encoded): ?string
    {
        if (!$encoded) {
            return null;
        }
        try {
            return $this->decrypt($encoded);
        } catch (\Throwable) {
            return null;
        }
    }
}
