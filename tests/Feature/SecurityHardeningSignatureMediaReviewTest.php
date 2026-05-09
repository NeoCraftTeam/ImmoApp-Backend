<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\LeaseContract;
use App\Models\LeaseSignatureRequest;
use App\Models\Review;
use App\Models\User;
use App\Notifications\LeaseSignatureOtpNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('rejects lease signature without otp after otp was required', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);
    $lease = LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
    ]);

    $signature = LeaseSignatureRequest::query()->create([
        'lease_contract_id' => $lease->id,
        'requested_by' => $owner->id,
        'signer_email' => 'tenant@example.com',
        'signer_name' => 'Locataire Test',
        'token' => Str::random(64),
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);

    $token = $signature->token;

    $this->postJson("/api/v1/signatures/{$token}/sign", [])->assertUnprocessable();
});

it('sends otp and completes sign flow with correct code', function (): void {
    Notification::fake();

    $owner = User::factory()->agents()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);
    $lease = LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
    ]);

    $signature = LeaseSignatureRequest::query()->create([
        'lease_contract_id' => $lease->id,
        'requested_by' => $owner->id,
        'signer_email' => 'tenant@example.com',
        'signer_name' => 'Locataire Test',
        'token' => Str::random(64),
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);

    $token = $signature->token;

    $this->postJson("/api/v1/signatures/{$token}/send-otp")->assertOk();

    $row = LeaseSignatureRequest::query()->where('token', $token)->first();
    expect($row?->sign_otp_hash)->not->toBeNull();

    $otp = null;
    Notification::assertSentOnDemand(LeaseSignatureOtpNotification::class, function (LeaseSignatureOtpNotification $n) use (&$otp): bool {
        $otp = $n->plainCode;

        return true;
    });
    expect($otp)->toBeString()->not->toBe('');

    $this->postJson("/api/v1/signatures/{$token}/sign", [
        'otp' => $otp,
    ])->assertOk()->assertJsonFragment(['message' => 'Contrat signé avec succès.']);

    expect(LeaseSignatureRequest::query()->where('token', $token)->first()?->status)->toBe('signed');
});

it('exposes otp requirement flag on public signature show', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);
    $lease = LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
    ]);

    $signature = LeaseSignatureRequest::query()->create([
        'lease_contract_id' => $lease->id,
        'requested_by' => $owner->id,
        'signer_email' => 'tenant@example.com',
        'signer_name' => 'Locataire Test',
        'token' => Str::random(64),
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);

    $this->getJson("/api/v1/signatures/{$signature->token}")
        ->assertOk()
        ->assertJsonPath('security.otp_required_for_sign_or_decline', true);
});

it('allows media proxy only for authenticated user who can update the parent ad', function (): void {
    config(['media-library.disk_name' => 'public']);
    Storage::fake('public');

    $owner = User::factory()->agents()->create();
    $other = User::factory()->agents()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    $ad->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('images');
    $media = $ad->getFirstMedia('images');
    expect($media)->not->toBeNull();

    $this->get(route('media.proxy', ['uuid' => $media->uuid]))
        ->assertForbidden();

    $this->actingAs($owner)->get(route('media.proxy', ['uuid' => $media->uuid]))
        ->assertOk();

    $this->actingAs($other)->get(route('media.proxy', ['uuid' => $media->uuid]))
        ->assertForbidden();
});

it('prevents duplicate owner response to the same review after first success', function (): void {
    $owner = User::factory()->agents()->create();
    $customer = User::factory()->customers()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);
    $review = Review::factory()->create([
        'ad_id' => $ad->id,
        'user_id' => $customer->id,
        'owner_response' => null,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/reviews/{$review->id}/respond", ['response' => 'Merci pour votre retour.'])
        ->assertOk();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/reviews/{$review->id}/respond", ['response' => 'Deuxième tentative.'])
        ->assertUnprocessable();
});
