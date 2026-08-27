<?php

declare(strict_types=1);

use App\Enums\LeaseStatus;
use App\Models\Ad;
use App\Models\Expense;
use App\Models\LeaseContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function profitLossAd(User $owner): Ad
{
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    return $ad;
}

// TC-EPL-01 — net_income = cumulative accrued rent − cumulative expenses.
// Guards against the previous bug where contract_revenue summed monthly_rent
// across ALL contracts (incl. terminated/draft), mixing a monthly figure with
// all-time expenses and making net_income meaningless.
it('reports profit/loss from cumulative accrued rent, not the raw monthly-rent sum', function (): void {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $owner = User::factory()->agents()->create();
    $ad = profitLossAd($owner);

    // Active since 2026-03-15 → 3 whole months × 100000 = 300000.
    LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
        'status' => LeaseStatus::Active,
        'lease_start' => '2026-03-15',
        'lease_end' => '2027-03-15',
        'monthly_rent' => 100000,
    ]);

    // Terminated: in force 2025-12-15 → 2026-04-15 = 4 months × 50000 = 200000.
    LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
        'status' => LeaseStatus::Terminated,
        'lease_start' => '2025-12-15',
        'lease_end' => '2026-12-15',
        'terminated_at' => '2026-04-15',
        'monthly_rent' => 50000,
    ]);

    // Draft carries no obligations yet → contributes 0 despite a huge rent.
    LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
        'status' => LeaseStatus::Draft,
        'lease_start' => '2026-01-15',
        'lease_end' => '2027-01-15',
        'monthly_rent' => 999999,
    ]);

    // Expired full term 2025-04-15 → 2026-04-15 = 12 months × 10000 = 120000.
    LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
        'status' => LeaseStatus::Expired,
        'lease_start' => '2025-04-15',
        'lease_end' => '2026-04-15',
        'monthly_rent' => 10000,
    ]);

    Expense::create([
        'ad_id' => $ad->id,
        'user_id' => $owner->id,
        'category' => 'maintenance',
        'amount' => 75000,
        'expense_date' => '2026-05-01',
    ]);
    Expense::create([
        'ad_id' => $ad->id,
        'user_id' => $owner->id,
        'category' => 'travaux',
        'amount' => 50000,
        'expense_date' => '2026-06-01',
    ]);

    Sanctum::actingAs($owner);
    $data = $this->getJson("/api/v1/my/ads/{$ad->id}/profit-loss")
        ->assertOk()
        ->json('data');

    // 300000 + 200000 + 0 (draft) + 120000 = 620000 — NOT the raw monthly sum
    // (100000 + 50000 + 999999 + 10000 = 1160099).
    expect((float) $data['contract_revenue'])->toBe(620000.0)
        ->and((float) $data['total_expenses'])->toBe(125000.0)
        ->and((float) $data['net_income'])->toBe(495000.0);

    expect($data['expenses_by_category'])->toHaveKeys(['maintenance', 'travaux']);

    Carbon::setTestNow();
});

// TC-EPL-02 — accruedRentToDate accrues nothing before the lease starts.
it('accrues nothing for a future-dated or start-less lease', function (): void {
    $asOf = Carbon::parse('2026-06-15 12:00:00');

    $future = LeaseContract::factory()->make([
        'user_id' => (string) Str::uuid(),
        'ad_id' => (string) Str::uuid(),
        'status' => LeaseStatus::Active,
        'lease_start' => '2026-09-15',
        'lease_end' => '2027-09-15',
        'monthly_rent' => 80000,
    ]);

    $noStart = LeaseContract::factory()->make([
        'user_id' => (string) Str::uuid(),
        'ad_id' => (string) Str::uuid(),
        'status' => LeaseStatus::Active,
        'lease_start' => null,
        'monthly_rent' => 80000,
    ]);

    expect($future->accruedRentToDate($asOf))->toBe(0.0)
        ->and($noStart->accruedRentToDate($asOf))->toBe(0.0);
});
