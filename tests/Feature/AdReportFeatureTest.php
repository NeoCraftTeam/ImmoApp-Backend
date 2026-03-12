<?php

declare(strict_types=1);

use App\Enums\AdReportStatus;
use App\Models\Ad;
use App\Models\AdReport;
use App\Models\User;
use App\Notifications\AdReportReceivedNotification;
use App\Notifications\NewAdListingReportNotification;
use Illuminate\Support\Facades\Notification;

it('allows an authenticated customer to report an ad and notifies admins', function (): void {
    Notification::fake();

    $owner = User::factory()->agents()->create();
    $admin = User::factory()->admin()->create();
    $reporter = User::factory()->customers()->create();

    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    $response = $this
        ->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/ads/{$ad->id}/reports", [
            'reason' => 'scam',
            'scam_reason' => 'asked_off_platform_payment',
            'payment_methods' => ['bank_transfer', 'cash'],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', AdReportStatus::PENDING->value);

    $report = AdReport::query()->first();
    expect($report)->not()->toBeNull();
    expect($report->ad_id)->toBe($ad->id);
    expect($report->reporter_id)->toBe($reporter->id);
    expect($report->owner_id)->toBe($owner->id);
    expect($report->payment_methods)->toBe(['bank_transfer', 'cash']);

    Notification::assertSentTo($admin, NewAdListingReportNotification::class);
    Notification::assertSentTo($reporter, AdReportReceivedNotification::class);
});

it('rejects reporting your own ad', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    $this
        ->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/ads/{$ad->id}/reports", [
            'reason' => 'inaccurate',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('prevents duplicate open reports for the same ad and reporter', function (): void {
    $owner = User::factory()->agents()->create();
    $reporter = User::factory()->customers()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    AdReport::factory()->create([
        'ad_id' => $ad->id,
        'reporter_id' => $reporter->id,
        'owner_id' => $owner->id,
        'status' => AdReportStatus::PENDING,
    ]);

    $this
        ->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/ads/{$ad->id}/reports", [
            'reason' => 'inaccurate',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('requires payment methods for off-platform payment scam reports', function (): void {
    $owner = User::factory()->agents()->create();
    $reporter = User::factory()->customers()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    $this
        ->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/ads/{$ad->id}/reports", [
            'reason' => 'scam',
            'scam_reason' => 'asked_off_platform_payment',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment_methods']);
});

it('rejects scam-only fields when reason is not scam', function (): void {
    $owner = User::factory()->agents()->create();
    $reporter = User::factory()->customers()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    $this
        ->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/ads/{$ad->id}/reports", [
            'reason' => 'inaccurate',
            'scam_reason' => 'shared_contacts',
            'payment_methods' => ['cash'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['scam_reason', 'payment_methods']);
});

it('requires at least 10 characters for other reason description', function (): void {
    $owner = User::factory()->agents()->create();
    $reporter = User::factory()->customers()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    $this
        ->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/ads/{$ad->id}/reports", [
            'reason' => 'other',
            'description' => 'court',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);
});
