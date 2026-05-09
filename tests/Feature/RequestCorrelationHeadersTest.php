<?php

declare(strict_types=1);

use App\Models\Ad;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('api responses echo x-request-id and x-correlation-id from client', function (): void {
    Ad::factory()->create(['status' => 'available']);

    $response = $this->getJson('/api/v1/ads', [
        'X-Request-ID' => '11111111-1111-4111-8111-111111111111',
        'X-Correlation-ID' => '22222222-2222-4222-8222-222222222222',
    ]);

    $response->assertOk()
        ->assertHeader('X-Request-ID', '11111111-1111-4111-8111-111111111111')
        ->assertHeader('X-Correlation-ID', '22222222-2222-4222-8222-222222222222');
});

test('invalid x-request-id is replaced and echoed as a new uuid', function (): void {
    Ad::factory()->create(['status' => 'available']);

    $response = $this->getJson('/api/v1/ads', [
        'X-Request-ID' => '../../../etc/passwd',
    ]);

    $response->assertOk();
    $header = $response->headers->get('X-Request-ID');
    expect($header)->not->toBe('../../../etc/passwd');
    expect($header)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
});

test('x-correlation-id falls back to request id when omitted', function (): void {
    Ad::factory()->create(['status' => 'available']);

    $response = $this->getJson('/api/v1/ads', [
        'X-Request-ID' => '33333333-3333-4333-8333-333333333333',
    ]);

    $response->assertOk()
        ->assertHeader('X-Request-ID', '33333333-3333-4333-8333-333333333333')
        ->assertHeader('X-Correlation-ID', '33333333-3333-4333-8333-333333333333');
});

test('invalid x-correlation-id is ignored and falls back to request id', function (): void {
    Ad::factory()->create(['status' => 'available']);

    $response = $this->getJson('/api/v1/ads', [
        'X-Request-ID' => '44444444-4444-4444-8444-444444444444',
        'X-Correlation-ID' => 'not-a-valid-token!!!',
    ]);

    $response->assertOk()
        ->assertHeader('X-Request-ID', '44444444-4444-4444-8444-444444444444')
        ->assertHeader('X-Correlation-ID', '44444444-4444-4444-8444-444444444444');
});
