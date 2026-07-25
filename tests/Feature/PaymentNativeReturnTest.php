<?php

declare(strict_types=1);

/**
 * Pont de retour natif : reçoit la redirection HTTPS de la passerelle et
 * renvoie un 302 vers le deep-link de l'app pour fermer l'onglet in-app.
 */
it('redirects to the native deep-link and forwards gateway params', function (): void {
    $response = $this->get(route('payment.native-return', [
        'callback' => 'keyhome://credits/callback',
        'tx_ref' => 'KH-ABC123',
        'status' => 'successful',
        'reference' => 'gw-ref-9',
    ]));

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->toStartWith('keyhome://credits/callback?')
        ->and($location)->toContain('tx_ref=KH-ABC123')
        ->and($location)->toContain('status=successful')
        ->and($location)->toContain('reference=gw-ref-9');
});

it('accepts the owners scheme', function (): void {
    $response = $this->get(route('payment.native-return', [
        'callback' => 'keyhomeowners://payment-success',
        'tx_ref' => 'KH-XYZ',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('keyhomeowners://payment-success?')
        ->and($response->headers->get('Location'))->toContain('tx_ref=KH-XYZ');
});

it('falls back to the frontend url when the callback is missing', function (): void {
    $response = $this->get(route('payment.native-return'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toBe(
        rtrim((string) config('app.frontend_url', config('app.url')), '/'),
    );
});

it('rejects a non-whitelisted https origin (anti open-redirect)', function (): void {
    $response = $this->get(route('payment.native-return', [
        'callback' => 'https://evil.example.com/steal',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toContain('evil.example.com')
        ->and($response->headers->get('Location'))->toBe(
            rtrim((string) config('app.frontend_url', config('app.url')), '/'),
        );
});

it('rejects an arbitrary custom scheme', function (): void {
    $response = $this->get(route('payment.native-return', [
        'callback' => 'javascript://alert(1)',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toBe(
        rtrim((string) config('app.frontend_url', config('app.url')), '/'),
    );
});
