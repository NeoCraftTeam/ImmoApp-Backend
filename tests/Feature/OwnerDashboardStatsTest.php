<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\Conversation;
use App\Models\LeaseContract;
use App\Models\RentPayment;
use App\Models\TentativeReservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow(null);
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/my/stats')->assertUnauthorized();
});

it('returns correct occupancy_rate', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01'));

    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ads = [];
    Ad::withoutSyncingToSearch(function () use (&$ads, $owner): void {
        $ads = Ad::factory()->count(4)->create([
            'user_id' => $owner->id,
            'status' => 'available',
        ]);
    });

    // 2 active leases out of 4 ads → 50 %
    LeaseContract::factory()->count(2)->create([
        'user_id' => $owner->id,
        'ad_id' => $ads[0]->id,
        'lease_start' => '2026-01-01',
        'lease_end' => '2026-12-31',
    ]);

    // 1 expired lease — should NOT count
    LeaseContract::factory()->expired()->create([
        'user_id' => $owner->id,
        'ad_id' => $ads[1]->id,
    ]);

    $response = $this->getJson('/api/v1/my/stats')->assertOk();

    $data = $response->json('data');
    expect($data['active_ads_count'])->toBe(4)
        ->and($data['active_leases_count'])->toBe(2)
        ->and($data['occupancy_rate'])->toEqual(50);
});

it('returns zero occupancy_rate when there are no published ads', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/v1/my/stats')->assertOk();

    expect($response->json('data.occupancy_rate'))->toEqual(0);
});

it('counts pending and confirmed viewings correctly', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    $client = User::factory()->create();

    TentativeReservation::factory()->pending()->create(['ad_id' => $ad->id, 'client_id' => $client->id, 'slot_starts_at' => '09:00:00', 'slot_ends_at' => '09:30:00']);
    TentativeReservation::factory()->pending()->create(['ad_id' => $ad->id, 'client_id' => User::factory()->create()->id, 'slot_starts_at' => '10:00:00', 'slot_ends_at' => '10:30:00']);
    TentativeReservation::factory()->confirmed()->create(['ad_id' => $ad->id, 'client_id' => User::factory()->create()->id, 'slot_starts_at' => '11:00:00', 'slot_ends_at' => '11:30:00']);

    // cancelled — should NOT count
    TentativeReservation::factory()->cancelled()->create(['ad_id' => $ad->id, 'client_id' => User::factory()->create()->id, 'slot_starts_at' => '14:00:00', 'slot_ends_at' => '14:30:00']);

    $data = $this->getJson('/api/v1/my/stats')->assertOk()->json('data');

    expect($data['pending_viewings_count'])->toBe(2)
        ->and($data['confirmed_viewings_count'])->toBe(1);
});

it('counts active boosts correctly', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    // Active boost — must insert a valid boost_pack first (FK constraint)
    $boostPackId = Str::uuid();
    DB::table('boost_packs')->insert([
        'id' => $boostPackId,
        'name' => 'Test Pack',
        'slug' => 'test-pack',
        'boost_score' => 5,
        'duration_days' => 7,
        'price_credits' => 10,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('ad_boosts')->insert([
        'id' => Str::uuid(),
        'ad_id' => $ad->id,
        'user_id' => $owner->id,
        'boost_pack_id' => $boostPackId,
        'credits_spent' => 10,
        'boost_score' => 5,
        'duration_days' => 7,
        'started_at' => now()->subDay(),
        'expires_at' => now()->addDays(6),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $data = $this->getJson('/api/v1/my/stats')->assertOk()->json('data');

    expect($data['active_boosts_count'])->toBe(1);
});

it('counts unread conversations correctly', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $tenant = User::factory()->create();
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    // Unread: last_message_at is after landlord_last_read_at
    Conversation::factory()->create([
        'landlord_id' => $owner->id,
        'tenant_id' => $tenant->id,
        'ad_id' => $ad->id,
        'last_message_at' => now(),
        'landlord_last_read_at' => now()->subHour(),
    ]);

    // Unread: landlord_last_read_at is null
    Conversation::factory()->create([
        'landlord_id' => $owner->id,
        'tenant_id' => User::factory()->create()->id,
        'ad_id' => $ad->id,
        'last_message_at' => now(),
        'landlord_last_read_at' => null,
    ]);

    // Read: landlord_last_read_at >= last_message_at
    Conversation::factory()->create([
        'landlord_id' => $owner->id,
        'tenant_id' => User::factory()->create()->id,
        'ad_id' => $ad->id,
        'last_message_at' => now()->subHour(),
        'landlord_last_read_at' => now(),
    ]);

    $data = $this->getJson('/api/v1/my/stats')->assertOk()->json('data');

    expect($data['unread_conversations_count'])->toBe(2);
});

it('returns monthly_rent_total for active leases only', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01'));

    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
        'lease_start' => '2026-01-01',
        'lease_end' => '2026-12-31',
        'monthly_rent' => 250000,
    ]);

    // expired
    LeaseContract::factory()->expired()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
        'monthly_rent' => 100000,
    ]);

    $data = $this->getJson('/api/v1/my/stats')->assertOk()->json('data');

    expect($data['monthly_rent_total_xaf'])->toEqual(250000);
});

it('returns rent_collected_xaf_30d for the landlord — last 30 days only', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01'));

    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    $lease = LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
    ]);

    // Two collections inside the 30-day window — should sum.
    RentPayment::factory()->create([
        'lease_contract_id' => $lease->id,
        'recorded_by_user_id' => $owner->id,
        'amount' => 150000,
        'received_at' => '2026-05-20',
    ]);
    RentPayment::factory()->create([
        'lease_contract_id' => $lease->id,
        'recorded_by_user_id' => $owner->id,
        'amount' => 75000,
        'received_at' => '2026-06-01',
    ]);

    // Outside the 30-day window — should NOT count.
    RentPayment::factory()->create([
        'lease_contract_id' => $lease->id,
        'recorded_by_user_id' => $owner->id,
        'amount' => 999999,
        'received_at' => '2026-04-01',
    ]);

    // Belongs to another landlord — should NOT count.
    $otherLease = LeaseContract::factory()->create();
    RentPayment::factory()->create([
        'lease_contract_id' => $otherLease->id,
        'amount' => 500000,
        'received_at' => '2026-05-25',
    ]);

    $data = $this->getJson('/api/v1/my/stats')->assertOk()->json('data');

    expect($data['rent_collected_xaf_30d'])->toBe(225000);
});
