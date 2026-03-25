<?php

declare(strict_types=1);

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Filament registers domain-scoped routes when FILAMENT_*_DOMAIN is set (typical local .env).
 * Falls back to path-based URLs when domains are empty (e.g. CI).
 */
function filamentPanelUrl(string $pathSegment): string
{
    $domainKey = match ($pathSegment) {
        'admin' => 'filament.panels.admin_domain',
        'agency' => 'filament.panels.agency_domain',
        default => 'filament.panels.owner_domain',
    };

    $host = config($domainKey);

    if (is_string($host) && $host !== '') {
        return 'https://'.$host.'/';
    }

    return '/'.$pathSegment;
}

it('redirects a logged-in agent to login instead of 403 when opening the admin panel', function (): void {
    $agent = User::factory()->agents()->create();

    $response = $this->actingAs($agent)->get(filamentPanelUrl('admin'));

    $response->assertRedirect();
    $response->assertSessionMissing('errors');
});

it('redirects a logged-in individual agent away from the agency panel', function (): void {
    $individual = User::factory()->agents()->create(['type' => UserType::INDIVIDUAL]);

    $response = $this->actingAs($individual)->get(filamentPanelUrl('agency'));

    $response->assertRedirect();
});
