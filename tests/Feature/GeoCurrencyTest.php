<?php

declare(strict_types=1);

use App\Services\Geo\GeoLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

it('resolves currency from the CF-IPCountry header when no MaxMind DB is present', function (): void {
    // Pas de base MaxMind en test → repli sur l'en-tête edge.
    config()->set('services.maxmind.db_path', '/nonexistent/GeoLite2-Country.mmdb');

    $this->getJson('/api/v1/geo/currency', ['CF-IPCountry' => 'CH'])
        ->assertOk()
        ->assertJson(['country' => 'CH', 'currency' => 'CHF', 'source' => 'ip']);
});

it('maps a euro-zone country to EUR', function (): void {
    config()->set('services.maxmind.db_path', '/nonexistent/GeoLite2-Country.mmdb');

    $this->getJson('/api/v1/geo/currency', ['CF-IPCountry' => 'FR'])
        ->assertOk()
        ->assertJson(['country' => 'FR', 'currency' => 'EUR']);
});

it('falls back to the base currency (XAF) when nothing can be resolved', function (): void {
    config()->set('services.maxmind.db_path', '/nonexistent/GeoLite2-Country.mmdb');

    $this->getJson('/api/v1/geo/currency')
        ->assertOk()
        ->assertJson(['country' => null, 'currency' => 'XAF', 'source' => 'fallback']);
});

it('ignores Cloudflare unknown country codes (XX / T1)', function (): void {
    config()->set('services.maxmind.db_path', '/nonexistent/GeoLite2-Country.mmdb');

    $this->getJson('/api/v1/geo/currency', ['CF-IPCountry' => 'XX'])
        ->assertOk()
        ->assertJson(['country' => null, 'currency' => 'XAF']);
});

it('returns null country for a private IP with no header (service unit)', function (): void {
    config()->set('services.maxmind.db_path', '/nonexistent/GeoLite2-Country.mmdb');

    $service = app(GeoLocationService::class);
    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

    expect($service->countryForRequest($request))->toBeNull()
        ->and($service->currencyForRequest($request)['currency'])->toBe('XAF');
});
