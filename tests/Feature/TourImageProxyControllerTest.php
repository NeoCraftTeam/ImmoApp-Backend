<?php

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use App\Support\TourAssetToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('GET /tour-image/{adId}/{path}', function (): void {
    it('forbids guest access without token', function (): void {
        Storage::fake(config('filesystems.default'));

        $owner = User::factory()->agents()->create();
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
            $ad = Ad::factory()->create([
                'status' => AdStatus::AVAILABLE,
                'user_id' => $owner->id,
                'has_3d_tour' => true,
            ]);
        });

        $path = "ads/{$ad->id}/tours/salon.webp";
        Storage::disk()->put($path, 'fake-image-content');

        $this->get("/tour-image/{$ad->id}/salon.webp")
            ->assertForbidden();
    });

    it('allows owner session access without token', function (): void {
        Storage::fake(config('filesystems.default'));

        $owner = User::factory()->agents()->create();
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
            $ad = Ad::factory()->create([
                'status' => AdStatus::AVAILABLE,
                'user_id' => $owner->id,
                'has_3d_tour' => true,
            ]);
        });

        Storage::disk()->put("ads/{$ad->id}/tours/salon.webp", 'fake-image-content');

        $this->actingAs($owner)
            ->get("/tour-image/{$ad->id}/salon.webp")
            ->assertOk();
    });

    it('allows temporary signed token access for guest', function (): void {
        Storage::fake(config('filesystems.default'));

        $owner = User::factory()->agents()->create();
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
            $ad = Ad::factory()->create([
                'status' => AdStatus::AVAILABLE,
                'user_id' => $owner->id,
                'has_3d_tour' => true,
            ]);
        });

        Storage::disk()->put("ads/{$ad->id}/tours/salon.webp", 'fake-image-content');

        $token = TourAssetToken::issue((string) $ad->id, 600);
        $url = "/tour-image/{$ad->id}/__t/{$token['exp']}/{$token['sig']}/salon.webp";

        $this->get($url)->assertOk();
    });

    it('rejects an expired signed token', function (): void {
        Storage::fake(config('filesystems.default'));

        $owner = User::factory()->agents()->create();
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
            $ad = Ad::factory()->create([
                'status' => AdStatus::AVAILABLE,
                'user_id' => $owner->id,
                'has_3d_tour' => true,
            ]);
        });

        Storage::disk()->put("ads/{$ad->id}/tours/salon.webp", 'fake-image-content');

        $exp = time() - 10;
        $sig = hash_hmac('sha256', "{$ad->id}|{$exp}", (string) config('app.key'));
        $url = "/tour-image/{$ad->id}/__t/{$exp}/{$sig}/salon.webp";

        $this->get($url)->assertForbidden();
    });

    it('rejects a tampered token signature', function (): void {
        Storage::fake(config('filesystems.default'));

        $owner = User::factory()->agents()->create();
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
            $ad = Ad::factory()->create([
                'status' => AdStatus::AVAILABLE,
                'user_id' => $owner->id,
                'has_3d_tour' => true,
            ]);
        });

        Storage::disk()->put("ads/{$ad->id}/tours/salon.webp", 'fake-image-content');

        $token = TourAssetToken::issue((string) $ad->id, 600);
        $url = "/tour-image/{$ad->id}/__t/{$token['exp']}/tampered_signature_value_here_1234567890abcdef/salon.webp";

        $this->get($url)->assertForbidden();
    });

    it('returns 404 for non-UUID adId', function (): void {
        $this->get('/tour-image/not-a-uuid/salon.webp')
            ->assertNotFound();
    });
});
