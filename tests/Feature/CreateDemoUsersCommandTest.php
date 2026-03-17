<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Mail\AdminWelcomeEmail;
use App\Mail\AgencyWelcomeEmail;
use App\Mail\BailleurWelcomeEmail;
use App\Mail\WelcomeEmail;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('creates all four demo users and sends role-specific welcome emails', function (): void {
    Mail::fake();

    $this->artisan('app:create-test-users')->assertSuccessful();

    $admin = User::where('email', 'test-admin-nc@proton.me')->first();
    $agencyUser = User::where('email', 'test-prof-nc@proton.me')->first();
    $bailleur = User::where('email', 'test-student-nc@proton.me')->first();
    $client = User::where('email', 'test-client-nc@proton.me')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe(UserRole::ADMIN)
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->must_change_password_at)->not->toBeNull();

    expect($agencyUser)->not->toBeNull()
        ->and($agencyUser->role)->toBe(UserRole::AGENT)
        ->and($agencyUser->type)->toBe(UserType::AGENCY)
        ->and($agencyUser->agency_id)->not->toBeNull();

    expect($bailleur)->not->toBeNull()
        ->and($bailleur->role)->toBe(UserRole::AGENT)
        ->and($bailleur->type)->toBe(UserType::INDIVIDUAL)
        ->and($bailleur->agency_id)->not->toBeNull();

    expect($client)->not->toBeNull()
        ->and($client->role)->toBe(UserRole::CUSTOMER)
        ->and($client->email_verified_at)->not->toBeNull();

    Mail::assertQueued(AdminWelcomeEmail::class, fn ($m) => $m->hasTo('test-admin-nc@proton.me'));
    Mail::assertQueued(AgencyWelcomeEmail::class, fn ($m) => $m->hasTo('test-prof-nc@proton.me'));
    Mail::assertQueued(BailleurWelcomeEmail::class, fn ($m) => $m->hasTo('test-student-nc@proton.me'));
    Mail::assertQueued(WelcomeEmail::class, fn ($m) => $m->hasTo('test-client-nc@proton.me'));
});

it('skips existing users and does not duplicate', function (): void {
    Mail::fake();
    User::factory()->create(['email' => 'test-admin-nc@proton.me', 'role' => UserRole::ADMIN]);

    $this->artisan('app:create-test-users')->assertSuccessful();

    expect(User::where('email', 'test-admin-nc@proton.me')->count())->toBe(1);
    Mail::assertNotQueued(AdminWelcomeEmail::class);
});

it('creates agency with correct name for agency user', function (): void {
    Mail::fake();

    $this->artisan('app:create-test-users')->assertSuccessful();

    $agencyUser = User::where('email', 'test-prof-nc@proton.me')->first();
    $agency = Agency::find($agencyUser->agency_id);

    expect($agency)->not->toBeNull()
        ->and($agency->name)->toBe('Agence Test KeyHome')
        ->and($agency->owner_id)->toBe($agencyUser->id);
});
