<?php

declare(strict_types=1);

use App\Services\AiSearchService;

/**
 * Targets the robust JSON extractor that replaces the previous shallow regex
 * (which broke on nested objects, code fences, and multi-line preambles from
 * LLM providers).
 */
beforeEach(function (): void {
    $this->extractJson = new ReflectionClass(AiSearchService::class)
        ->getMethod('extractJson');
    $this->service = app(AiSearchService::class);
});

it('extracts a plain JSON object', function (): void {
    $result = $this->extractJson->invoke(
        $this->service,
        '{"transaction_type":"location","price_max":250000}',
    );

    expect($result)->toBe([
        'transaction_type' => 'location',
        'price_max' => 250000,
    ]);
});

it('extracts JSON wrapped in ```json fences', function (): void {
    $payload = "```json\n{\"city_name\":\"Douala\",\"bedrooms\":3}\n```";

    $result = $this->extractJson->invoke($this->service, $payload);

    expect($result)->toBe([
        'city_name' => 'Douala',
        'bedrooms' => 3,
    ]);
});

it('handles nested objects without breaking the brace scanner', function (): void {
    $payload = '{"q":"villa","filters":{"min_surface":100,"options":{"pool":true}}}';

    $result = $this->extractJson->invoke($this->service, $payload);

    expect($result['filters']['options']['pool'])->toBeTrue();
    expect($result['filters']['min_surface'])->toBe(100);
});

it('handles strings containing braces and escapes', function (): void {
    $payload = 'Voici votre JSON : {"q":"villa avec {garage}","note":"{\\"k\\":1}"}';

    $result = $this->extractJson->invoke($this->service, $payload);

    expect($result['q'])->toBe('villa avec {garage}');
    expect($result['note'])->toBe('{"k":1}');
});

it('returns null for content with no JSON object', function (): void {
    $result = $this->extractJson->invoke($this->service, 'Désolé je ne comprends pas.');

    expect($result)->toBeNull();
});
