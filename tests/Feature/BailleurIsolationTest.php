<?php

use App\Filament\Bailleur\Resources\Ads\AdResource;
use App\Filament\Bailleur\Resources\Payments\PaymentResource;
use App\Models\Ad;
use App\Models\Payment;
use App\Models\Scopes\LandlordScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('LandlordScope', function (): void {
    it('filters records by authenticated user id', function (): void {
        $bailleurA = User::factory()->agents()->create();
        $bailleurB = User::factory()->agents()->create();

        Ad::withoutSyncingToSearch(function () use ($bailleurA, $bailleurB): void {
            Ad::factory()->count(3)->create(['user_id' => $bailleurA->id, 'status' => 'available']);
            Ad::factory()->count(2)->create(['user_id' => $bailleurB->id, 'status' => 'available']);
        });

        $this->actingAs($bailleurA);

        $query = Ad::query()->withGlobalScope('landlord', new LandlordScope);
        expect($query->count())->toBe(3);
    });

    it('does not apply when unauthenticated', function (): void {
        Ad::withoutSyncingToSearch(function (): void {
            Ad::factory()->count(2)->create(['status' => 'available']);
        });

        $query = Ad::query()->withGlobalScope('landlord', new LandlordScope);
        expect($query->count())->toBe(2);
    });
});

describe('Bailleur Ad isolation', function (): void {
    it('bailleur A cannot see bailleur B ads', function (): void {
        $bailleurA = User::factory()->agents()->create();
        $bailleurB = User::factory()->agents()->create();

        Ad::withoutSyncingToSearch(function () use ($bailleurA, $bailleurB): void {
            Ad::factory()->count(3)->create(['user_id' => $bailleurA->id, 'status' => 'available']);
            Ad::factory()->count(5)->create(['user_id' => $bailleurB->id, 'status' => 'available']);
        });

        $this->actingAs($bailleurA);

        $results = AdResource::getEloquentQuery()->get();

        expect($results)->toHaveCount(3);
        expect($results->pluck('user_id')->unique()->values()->all())
            ->toBe([$bailleurA->id]);
    });

    it('bailleur B only sees their own ads', function (): void {
        $bailleurA = User::factory()->agents()->create();
        $bailleurB = User::factory()->agents()->create();

        Ad::withoutSyncingToSearch(function () use ($bailleurA, $bailleurB): void {
            Ad::factory()->count(3)->create(['user_id' => $bailleurA->id, 'status' => 'available']);
            Ad::factory()->count(2)->create(['user_id' => $bailleurB->id, 'status' => 'available']);
        });

        $this->actingAs($bailleurB);

        $results = AdResource::getEloquentQuery()->get();

        expect($results)->toHaveCount(2);
        expect($results->pluck('user_id')->unique()->values()->all())
            ->toBe([$bailleurB->id]);
    });
});

describe('Bailleur Payment isolation', function (): void {
    it('bailleur A cannot see bailleur B payments', function (): void {
        $bailleurA = User::factory()->agents()->create();
        $bailleurB = User::factory()->agents()->create();

        Payment::factory()->count(4)->create(['user_id' => $bailleurA->id]);
        Payment::factory()->count(6)->create(['user_id' => $bailleurB->id]);

        $this->actingAs($bailleurA);

        $results = PaymentResource::getEloquentQuery()->get();

        expect($results)->toHaveCount(4);
        expect($results->pluck('user_id')->unique()->values()->all())
            ->toBe([$bailleurA->id]);
    });

    it('bailleur B only sees their own payments', function (): void {
        $bailleurA = User::factory()->agents()->create();
        $bailleurB = User::factory()->agents()->create();

        Payment::factory()->count(4)->create(['user_id' => $bailleurA->id]);
        Payment::factory()->count(3)->create(['user_id' => $bailleurB->id]);

        $this->actingAs($bailleurB);

        $results = PaymentResource::getEloquentQuery()->get();

        expect($results)->toHaveCount(3);
        expect($results->pluck('user_id')->unique()->values()->all())
            ->toBe([$bailleurB->id]);
    });
});
