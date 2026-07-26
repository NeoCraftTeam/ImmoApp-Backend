<?php

declare(strict_types=1);

/**
 * Les événements chat/crédits sont `ShouldBroadcastNow` : la diffusion part
 * DANS la requête HTTP. Sans timeout Guzzle, un Reverb injoignable en
 * half-open bloque le worker FPM jusqu'à max_execution_time et sature le
 * pool — toute l'API timeout. Ces assertions verrouillent la borne.
 */
it('bounds the reverb broadcaster http client with strict timeouts', function (): void {
    $options = config('broadcasting.connections.reverb.client_options');

    expect((float) $options['timeout'])->toBeGreaterThan(0)->toBeLessThanOrEqual(5)
        ->and((float) $options['connect_timeout'])->toBeGreaterThan(0)->toBeLessThanOrEqual(5);
});

it('bounds the pusher broadcaster http client with strict timeouts', function (): void {
    $options = config('broadcasting.connections.pusher.client_options');

    expect((float) $options['timeout'])->toBeGreaterThan(0)->toBeLessThanOrEqual(5)
        ->and((float) $options['connect_timeout'])->toBeGreaterThan(0)->toBeLessThanOrEqual(5);
});
