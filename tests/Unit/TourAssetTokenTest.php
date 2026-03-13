<?php

declare(strict_types=1);

use App\Support\TourAssetToken;

it('issues a token with exp and sig keys', function (): void {
    $token = TourAssetToken::issue('ad-123', 300);

    expect($token)->toHaveKeys(['exp', 'sig']);
    expect($token['exp'])->toBeInt()->toBeGreaterThan(time());
    expect($token['sig'])->toBeString()->toHaveLength(64);
});

it('validates a freshly issued token', function (): void {
    $token = TourAssetToken::issue('ad-123', 300);

    expect(TourAssetToken::validate('ad-123', (string) $token['exp'], $token['sig']))->toBeTrue();
});

it('rejects an expired token', function (): void {
    $exp = (string) (time() - 10);
    $sig = hash_hmac('sha256', "ad-123|{$exp}", (string) config('app.key'));

    expect(TourAssetToken::validate('ad-123', $exp, $sig))->toBeFalse();
});

it('rejects a tampered signature', function (): void {
    $token = TourAssetToken::issue('ad-123', 300);

    expect(TourAssetToken::validate('ad-123', (string) $token['exp'], 'tampered-signature'))->toBeFalse();
});

it('rejects a token issued for a different ad', function (): void {
    $token = TourAssetToken::issue('ad-123', 300);

    expect(TourAssetToken::validate('ad-999', (string) $token['exp'], $token['sig']))->toBeFalse();
});

it('rejects null or empty inputs', function (): void {
    expect(TourAssetToken::validate('ad-123', null, null))->toBeFalse();
    expect(TourAssetToken::validate('ad-123', '', ''))->toBeFalse();
    expect(TourAssetToken::validate('ad-123', 'not-a-number', 'abc'))->toBeFalse();
});

it('injects token into proxy path correctly', function (): void {
    $url = '/tour-image/ad-123/scene.jpg';
    $result = TourAssetToken::injectIntoProxyPath($url, 'ad-123', 9999999999, 'abcdef');

    expect($result)->toBe('/tour-image/ad-123/__t/9999999999/abcdef/scene.jpg');
});

it('signs all scene URLs in a tour config', function (): void {
    $config = [
        'default_scene' => 's1',
        'scenes' => [
            [
                'id' => 's1',
                'image_url' => '/tour-image/ad-123/salon.jpg',
                'tiles_base_url' => '/tour-image/ad-123/tiles',
                'fallback_base_url' => '/tour-image/ad-123/fallback',
                'cube_map' => ['/tour-image/ad-123/f.jpg', '/tour-image/ad-123/r.jpg'],
            ],
        ],
    ];

    $signed = TourAssetToken::signTourConfig('ad-123', $config);

    expect($signed['scenes'][0]['image_url'])->toContain('/__t/');
    expect($signed['scenes'][0]['tiles_base_url'])->toContain('/__t/');
    expect($signed['scenes'][0]['fallback_base_url'])->toContain('/__t/');
    expect($signed['scenes'][0]['cube_map'][0])->toContain('/__t/');
    expect($signed['scenes'][0]['cube_map'][1])->toContain('/__t/');
});

it('returns config unchanged when scenes key is missing', function (): void {
    $config = ['default_scene' => 's1'];

    $result = TourAssetToken::signTourConfig('ad-123', $config);

    expect($result)->toBe($config);
});
