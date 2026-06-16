<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\Conversation;
use App\Models\Quarter;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function uploadSecurityConversation(): array
{
    $tenant = User::factory()->create();
    $landlord = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $landlord->id]);

    UnlockedAd::create([
        'user_id' => $tenant->id,
        'ad_id' => $ad->id,
        'unlocked_at' => now(),
    ]);

    $conversation = Conversation::create([
        'ad_id' => $ad->id,
        'tenant_id' => $tenant->id,
        'landlord_id' => $landlord->id,
        'status' => ConversationStatus::Active,
    ]);

    return compact('tenant', 'conversation');
}

/**
 * Build a fake JPEG-named upload whose bytes are not a real image (polyglot / webshell probe).
 */
function maliciousJpegPolyglotUpload(string $name = 'photo.jpg'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'kh_poly_');
    file_put_contents($path, '<?php echo "webshell"; __halt_compiler();');

    return new UploadedFile($path, $name, 'image/jpeg', null, true);
}

it('rejects chat attachment that is not a real image despite jpg extension', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = uploadSecurityConversation();
    Storage::fake('r2');

    $file = maliciousJpegPolyglotUpload();

    $this->actingAs($tenant)
        ->post("/api/v1/conversations/{$conv->id}/attachments", [
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ])
        ->assertUnprocessable();
});

it('rejects ad image upload with php payload disguised as jpeg', function (): void {
    $agent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $quarter = Quarter::factory()->create();
    $adType = AdType::factory()->create();

    Sanctum::actingAs($agent, ['*']);

    $file = maliciousJpegPolyglotUpload('cover.jpg');

    $this->post('/api/v1/ads', [
        'title' => 'Annonce test sécurité',
        'description' => 'Description suffisamment longue pour la validation.',
        'adresse' => '123 Rue Test',
        'price' => 50000,
        'surface_area' => 80,
        'bedrooms' => 2,
        'bathrooms' => 1,
        'has_parking' => '1',
        'latitude' => 4.05,
        'longitude' => 9.76,
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
        'expires_at' => now()->addDays(30)->toDateTimeString(),
        'images' => [$file],
    ], [
        'Accept' => 'application/json',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['images.0']);
});

it('rejects property condition pdf without a valid pdf header', function (): void {
    $agent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $quarter = Quarter::factory()->create();
    $adType = AdType::factory()->create();

    Sanctum::actingAs($agent, ['*']);

    $path = tempnam(sys_get_temp_dir(), 'kh_fake_pdf_');
    file_put_contents($path, '<?php echo "not-a-pdf"; ?>');
    $fakePdf = new UploadedFile($path, 'etat.pdf', 'application/pdf', null, true);

    $this->post('/api/v1/ads', [
        'title' => 'Annonce PDF test',
        'description' => 'Description suffisamment longue pour la validation.',
        'adresse' => '456 Avenue Test',
        'price' => 75000,
        'surface_area' => 90,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'has_parking' => 'false',
        'latitude' => 4.05,
        'longitude' => 9.76,
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
        'expires_at' => now()->addDays(30)->toDateTimeString(),
        'property_condition' => $fakePdf,
    ], [
        'Accept' => 'application/json',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['property_condition']);
});

it('rejects chat attachment with dangerous double extension in filename', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = uploadSecurityConversation();
    Storage::fake('r2');

    $source = UploadedFile::fake()->image('avatar.jpg');
    $copyPath = sys_get_temp_dir().'/'.uniqid('kh_safe_', true).'.jpg';
    copy($source->getRealPath(), $copyPath);
    $dangerous = new UploadedFile($copyPath, 'photo.php.jpg', 'image/jpeg', null, true);

    $this->actingAs($tenant)
        ->post("/api/v1/conversations/{$conv->id}/attachments", [
            'file' => $dangerous,
        ], [
            'Accept' => 'application/json',
        ])
        ->assertUnprocessable();
});
