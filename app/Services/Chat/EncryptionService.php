<?php

declare(strict_types=1);

namespace App\Services\Chat;

use RuntimeException;

/**
 * AES-256-CBC symmetric encryption for chat message bodies.
 *
 * The encryption key is loaded from config('chat.encryption_key') which maps
 * to the CHAT_ENCRYPTION_KEY environment variable. The key must be a 64-char
 * hex string (32 bytes) — generate with: php -r "echo bin2hex(random_bytes(32));"
 *
 * SECURITY: Never log, expose in responses, or hard-code the key.
 */
final readonly class EncryptionService
{
    private const string CIPHER = 'aes-256-cbc';

    private string $key;

    public function __construct()
    {
        /** @var string|null $hexKey */
        $hexKey = config('chat.encryption_key');

        if ($hexKey === null || $hexKey === '') {
            throw new RuntimeException('CHAT_ENCRYPTION_KEY is not set. Run: php -r "echo bin2hex(random_bytes(32));"');
        }

        $this->key = (string) hex2bin($hexKey);
    }

    /**
     * Encrypt plaintext using AES-256-CBC.
     *
     * @return array{ciphertext: string, iv: string} Base64-encoded ciphertext + hex IV
     *
     * @throws RuntimeException on encryption failure
     */
    public function encrypt(string $plaintext): array
    {
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER) ?: 16);

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Message encryption failed.');
        }

        $hexIv = bin2hex($iv);
        $b64Cipher = base64_encode($ciphertext);

        // Prepend HMAC-SHA256 MAC (32 bytes) to the raw ciphertext so that
        // decrypt() can verify integrity before attempting decryption.
        $mac = hash_hmac('sha256', $hexIv.$b64Cipher, $this->key, true);
        $payload = base64_encode($mac.$ciphertext);

        return [
            'ciphertext' => $payload,
            'iv' => $hexIv,
        ];
    }

    /**
     * Decrypt ciphertext encrypted by {@see encrypt()}.
     *
     * @param  string  $ciphertext  Base64-encoded ciphertext
     * @param  string  $iv  Hex-encoded initialization vector
     *
     * @throws RuntimeException on decryption failure
     */
    public function decrypt(string $ciphertext, string $iv): string
    {
        $rawIv = hex2bin($iv);

        if ($rawIv === false) {
            throw new RuntimeException('Invalid IV: not a valid hex string.');
        }

        $decoded = base64_decode($ciphertext, true);

        if ($decoded === false) {
            throw new RuntimeException('Invalid ciphertext: not valid base64.');
        }

        // Authenticate the payload: HMAC-SHA256(key, hexIv || base64(raw_ciphertext))
        // Matches the MAC computation in encrypt(): hmac(hexIv + base64_encode(raw_ciphertext))
        $stored = substr($decoded, 0, 32);
        $encrypted = substr($decoded, 32);
        $expected = hash_hmac('sha256', $iv.base64_encode($encrypted), $this->key, true);

        if (!hash_equals($expected, $stored)) {
            throw new RuntimeException('Message decryption failed: authentication error.');
        }

        $plaintext = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $rawIv,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Message decryption failed.');
        }

        return $plaintext;
    }
}
