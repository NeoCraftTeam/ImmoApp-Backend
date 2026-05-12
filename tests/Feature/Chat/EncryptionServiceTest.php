<?php

declare(strict_types=1);

use App\Services\Chat\EncryptionService;

beforeEach(function (): void {
    config(['chat.encryption_key' => bin2hex(random_bytes(32))]);
});

it('encrypts and decrypts a plaintext message correctly', function (): void {
    $service = new EncryptionService;
    $plain = 'Bonjour, est-ce que l\'appartement est encore disponible ?';

    $result = $service->encrypt($plain);

    expect($result)->toHaveKeys(['ciphertext', 'iv'])
        ->and($result['ciphertext'])->not->toBe($plain)
        ->and($result['iv'])->toBeString()->not->toBeEmpty();

    $decrypted = $service->decrypt($result['ciphertext'], $result['iv']);

    expect($decrypted)->toBe($plain);
});

it('produces different ciphertext for the same plaintext (unique IVs)', function (): void {
    $service = new EncryptionService;
    $plain = 'Test message';

    $first = $service->encrypt($plain);
    $second = $service->encrypt($plain);

    expect($first['ciphertext'])->not->toBe($second['ciphertext'])
        ->and($first['iv'])->not->toBe($second['iv']);
});

it('throws an exception when the encryption key is missing', function (): void {
    config(['chat.encryption_key' => null]);

    expect(fn () => new EncryptionService)->toThrow(RuntimeException::class);
});

it('throws an exception when decrypting with a wrong IV', function (): void {
    $service = new EncryptionService;
    $result = $service->encrypt('test');

    // 32 hex chars = valid format but wrong value → MAC auth failure → RuntimeException
    expect(fn () => $service->decrypt($result['ciphertext'], str_repeat('00', 16)))
        ->toThrow(RuntimeException::class);
});
