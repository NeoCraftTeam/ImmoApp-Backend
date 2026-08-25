<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Agency;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Characterization tests locking the current behaviour of the Filament
 * contract methods before they move to Concerns\InteractsWithFilamentPanels.
 */

function fakePanel(string $id): Panel
{
    $panel = Mockery::mock(Panel::class);
    $panel->allows('getId')->andReturn($id);

    return $panel;
}

describe('canAccessPanel', function (): void {
    it('always grants admins access to any panel', function (): void {
        $admin = User::factory()->admin()->create();

        expect($admin->canAccessPanel(fakePanel('agency')))->toBeTrue()
            ->and($admin->canAccessPanel(fakePanel('bailleur')))->toBeTrue()
            ->and($admin->canAccessPanel(fakePanel('unknown')))->toBeTrue();
    });

    it('grants an agency agent the agency panel only', function (): void {
        $user = User::factory()->create(['role' => UserRole::AGENT, 'type' => UserType::AGENCY]);

        expect($user->canAccessPanel(fakePanel('agency')))->toBeTrue()
            ->and($user->canAccessPanel(fakePanel('bailleur')))->toBeFalse();
    });

    it('grants an individual agent the bailleur panel only', function (): void {
        $user = User::factory()->create(['role' => UserRole::AGENT, 'type' => UserType::INDIVIDUAL]);

        expect($user->canAccessPanel(fakePanel('bailleur')))->toBeTrue()
            ->and($user->canAccessPanel(fakePanel('agency')))->toBeFalse();
    });

    it('denies customers every panel', function (): void {
        $customer = User::factory()->customers()->create();

        expect($customer->canAccessPanel(fakePanel('agency')))->toBeFalse()
            ->and($customer->canAccessPanel(fakePanel('bailleur')))->toBeFalse();
    });
});

describe('getTenants', function (): void {
    it('returns every agency for admins', function (): void {
        Agency::factory()->count(2)->create();
        $admin = User::factory()->admin()->create();

        expect($admin->getTenants(fakePanel('agency')))->toHaveCount(2);
    });

    it('returns only the attached agency for a non-admin', function (): void {
        $agency = Agency::factory()->create();
        $user = User::factory()->create([
            'role' => UserRole::AGENT,
            'type' => UserType::AGENCY,
            'agency_id' => $agency->id,
        ]);
        $user->load('agency');

        $tenants = $user->getTenants(fakePanel('agency'));

        expect($tenants)->toHaveCount(1)
            ->and($tenants->first()->is($agency))->toBeTrue();
    });

    it('returns an empty collection for a non-admin with no agency', function (): void {
        $customer = User::factory()->customers()->create();
        $customer->load('agency');

        expect($customer->getTenants(fakePanel('agency')))->toHaveCount(0);
    });
});

describe('canAccessTenant', function (): void {
    it('lets an admin access any tenant', function (): void {
        $admin = User::factory()->admin()->create();
        $agency = Agency::factory()->create();

        expect($admin->canAccessTenant($agency))->toBeTrue();
    });

    it('lets a non-admin access only their own agency', function (): void {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $user = User::factory()->create([
            'role' => UserRole::AGENT,
            'type' => UserType::AGENCY,
            'agency_id' => $ownAgency->id,
        ]);

        expect($user->canAccessTenant($ownAgency))->toBeTrue()
            ->and($user->canAccessTenant($otherAgency))->toBeFalse();
    });
});

describe('display helpers', function (): void {
    it('builds the Filament name from first and last name', function (): void {
        $user = User::factory()->create(['firstname' => 'Jane', 'lastname' => 'Doe']);

        expect($user->getFilamentName())->toBe('Jane Doe');
    });

    it('returns a remote avatar URL verbatim', function (): void {
        $user = User::factory()->create();
        $user->avatar = 'https://cdn.example.com/avatar.png';

        expect($user->getFilamentAvatarUrl())->toBe('https://cdn.example.com/avatar.png');
    });

    it('returns null when no avatar is stored', function (): void {
        $user = User::factory()->create();
        $user->avatar = '';

        expect($user->getFilamentAvatarUrl())->toBeNull();
    });

    it('returns the disk URL when the stored avatar file exists', function (): void {
        $disk = config('filesystems.app_media_disk');
        Storage::fake($disk);
        Storage::disk($disk)->put('avatars/pic.webp', 'binary');

        $user = User::factory()->create();
        $user->avatar = 'avatars/pic.webp';

        expect($user->getFilamentAvatarUrl())->toBe(Storage::disk($disk)->url('avatars/pic.webp'));
    });
});
