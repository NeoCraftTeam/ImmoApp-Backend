<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('defaults weekly recurrence_days from starts_on so public slots are returned', function (): void {
    $owner = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);

    $saturday = Carbon::now()->startOfDay();
    if ($saturday->dayOfWeek !== Carbon::SATURDAY) {
        $saturday = $saturday->next(Carbon::SATURDAY);
    }

    $endsOn = $saturday->copy()->addDays(14);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/ads/{$ad->id}/availability", [
        'name' => 'Créneaux test',
        'starts_on' => $saturday->toDateString(),
        'ends_on' => $endsOn->toDateString(),
        'periods' => [['starts_at' => '09:00', 'ends_at' => '12:00']],
        'recurrence' => 'weekly',
        'slot_duration' => 60,
        'buffer_minutes' => 10,
    ])->assertCreated();

    $dateKey = $saturday->toDateString();

    $slotsResponse = $this->getJson("/api/v1/ads/{$ad->id}/slots?date={$dateKey}");

    $slotsResponse->assertOk();
    $daySlots = $slotsResponse->json('data.slots_by_date.'.$dateKey);
    expect($daySlots)->toBeArray();
    expect($daySlots)->not->toBeEmpty();
});
