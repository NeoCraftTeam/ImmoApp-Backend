<?php

declare(strict_types=1);

use App\Enums\ScreeningDocumentType;
use App\Enums\ScreeningStatus;
use App\Models\LeaseContract;
use App\Models\TenantScreeningDocument;
use App\Models\TenantScreeningRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ── Landlord: Create screening request ────────────────────────────

it('requires authentication to create a screening request', function (): void {
    $lease = LeaseContract::factory()->create();

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/screening", [
        'tenant_name' => 'Jean Dupont',
        'tenant_email' => 'jean@example.com',
        'required_documents' => ['id_card'],
    ])->assertUnauthorized();
});

it('forbids creating screening for another landlord\'s lease', function (): void {
    $owner = User::factory()->agents()->create();
    $other = User::factory()->agents()->create();
    Sanctum::actingAs($other);

    $lease = LeaseContract::factory()->create(['user_id' => $owner->id]);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/screening", [
        'tenant_name' => 'Jean Dupont',
        'tenant_email' => 'jean@example.com',
        'required_documents' => ['id_card'],
    ])->assertForbidden();
});

it('creates a screening request with a token', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $lease = LeaseContract::factory()->create(['user_id' => $owner->id]);

    $response = $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/screening", [
        'tenant_name' => 'Jean Dupont',
        'tenant_email' => 'jean@example.com',
        'required_documents' => ['id_card', 'salary_slip'],
        'landlord_notes' => 'Merci de fournir les 3 derniers bulletins.',
        'expires_in_days' => 7,
    ])->assertCreated();

    $data = $response->json('data');
    expect($data['status'])->toBe('pending')
        ->and($data['tenant_name'])->toBe('Jean Dupont')
        ->and($data['required_documents'])->toBe(['id_card', 'salary_slip']);

    // SECURITY: the token must be generated on the server but MUST NOT
    // leak through the landlord-facing API response. Verify the row was
    // persisted with a 64-char token without echoing it back to the
    // caller.
    expect($data)->not->toHaveKey('token');

    $persisted = TenantScreeningRequest::query()->where('id', $data['id'])->firstOrFail();
    expect(strlen((string) $persisted->token))->toBe(64);
});

it('validates required_documents contains valid types', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $lease = LeaseContract::factory()->create(['user_id' => $owner->id]);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/screening", [
        'tenant_name' => 'Jean Dupont',
        'tenant_email' => 'jean@example.com',
        'required_documents' => ['invalid_type'],
    ])->assertUnprocessable();
});

// ── Landlord: List & show ─────────────────────────────────────────

it('lists screening requests for a lease', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $lease = LeaseContract::factory()->create(['user_id' => $owner->id]);
    TenantScreeningRequest::factory()->count(2)->create([
        'lease_contract_id' => $lease->id,
        'requested_by' => $owner->id,
    ]);

    $response = $this->getJson("/api/v1/my/lease-contracts/{$lease->id}/screening")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

// ── Public: View screening request ────────────────────────────────

it('shows screening request publicly via token', function (): void {
    $screening = TenantScreeningRequest::factory()->create();

    $response = $this->getJson("/api/v1/screening/{$screening->token}")
        ->assertOk();

    expect($response->json('data.tenant_name'))->toBe($screening->tenant_name)
        ->and($response->json('data.status'))->toBe('pending');
});

it('marks expired screening when accessed via token', function (): void {
    $screening = TenantScreeningRequest::factory()->expired()->create([
        'status' => ScreeningStatus::Pending,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->getJson("/api/v1/screening/{$screening->token}")
        ->assertOk();

    expect($response->json('data.status'))->toBe('expired');
});

// ── Public: Upload documents ──────────────────────────────────────

it('allows tenant to upload a document via token', function (): void {
    $disk = config('filesystems.app_media_disk', 'local');
    Storage::fake($disk);

    $screening = TenantScreeningRequest::factory()->create();

    $response = $this->postJson("/api/v1/screening/{$screening->token}/upload", [
        'document_type' => 'id_card',
        'file' => UploadedFile::fake()->create('carte-id.pdf', 500, 'application/pdf'),
        'notes' => 'Recto-verso',
    ])->assertCreated();

    $data = $response->json('data');
    expect($data['document_type'])->toBe('id_card')
        ->and($data['original_name'])->toBe('carte-id.pdf')
        ->and($data['notes'])->toBe('Recto-verso');

    $doc = $screening->documents()->first();
    Storage::disk($disk)->assertExists($doc->path);
});

it('rejects upload to expired screening', function (): void {
    $screening = TenantScreeningRequest::factory()->create([
        'expires_at' => now()->subDay(),
    ]);

    $this->postJson("/api/v1/screening/{$screening->token}/upload", [
        'document_type' => 'id_card',
        'file' => UploadedFile::fake()->create('id.pdf', 500, 'application/pdf'),
    ])->assertStatus(410);
});

it('rejects upload to already-reviewed screening', function (): void {
    $screening = TenantScreeningRequest::factory()->approved()->create();

    $this->postJson("/api/v1/screening/{$screening->token}/upload", [
        'document_type' => 'id_card',
        'file' => UploadedFile::fake()->create('id.pdf', 500, 'application/pdf'),
    ])->assertStatus(409);
});

it('validates file type on upload', function (): void {
    $screening = TenantScreeningRequest::factory()->create();

    $this->postJson("/api/v1/screening/{$screening->token}/upload", [
        'document_type' => 'id_card',
        'file' => UploadedFile::fake()->create('malware.exe', 500, 'application/x-msdownload'),
    ])->assertUnprocessable();
});

// ── Public: Submit dossier ────────────────────────────────────────

it('allows tenant to submit their dossier', function (): void {
    Storage::fake(config('filesystems.app_media_disk', 'local'));

    $screening = TenantScreeningRequest::factory()->create();

    // Upload a document first
    $this->postJson("/api/v1/screening/{$screening->token}/upload", [
        'document_type' => 'id_card',
        'file' => UploadedFile::fake()->create('id.pdf', 500, 'application/pdf'),
    ])->assertCreated();

    $response = $this->postJson("/api/v1/screening/{$screening->token}/submit")
        ->assertOk();

    expect($response->json('data.status'))->toBe('submitted');
    expect($screening->fresh()->submitted_at)->not->toBeNull();
});

it('rejects submission with no documents uploaded', function (): void {
    $screening = TenantScreeningRequest::factory()->create();

    $this->postJson("/api/v1/screening/{$screening->token}/submit")
        ->assertUnprocessable();
});

// ── Landlord: Review ──────────────────────────────────────────────

it('allows landlord to approve a submitted dossier', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $lease = LeaseContract::factory()->create(['user_id' => $owner->id]);
    $screening = TenantScreeningRequest::factory()->submitted()->create([
        'lease_contract_id' => $lease->id,
        'requested_by' => $owner->id,
    ]);

    $response = $this->postJson(
        "/api/v1/my/lease-contracts/{$lease->id}/screening/{$screening->id}/review",
        ['decision' => 'approved', 'review_notes' => 'Dossier complet.']
    )->assertOk();

    expect($response->json('data.status'))->toBe('approved')
        ->and($screening->fresh()->reviewed_by)->toBe($owner->id);
});

it('allows landlord to reject a submitted dossier', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $lease = LeaseContract::factory()->create(['user_id' => $owner->id]);
    $screening = TenantScreeningRequest::factory()->submitted()->create([
        'lease_contract_id' => $lease->id,
        'requested_by' => $owner->id,
    ]);

    $response = $this->postJson(
        "/api/v1/my/lease-contracts/{$lease->id}/screening/{$screening->id}/review",
        ['decision' => 'rejected', 'review_notes' => 'Document illisible.']
    )->assertOk();

    expect($response->json('data.status'))->toBe('rejected');
});

it('rejects review of a non-submitted dossier', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $lease = LeaseContract::factory()->create(['user_id' => $owner->id]);
    $screening = TenantScreeningRequest::factory()->create([
        'lease_contract_id' => $lease->id,
        'requested_by' => $owner->id,
    ]);

    $this->postJson(
        "/api/v1/my/lease-contracts/{$lease->id}/screening/{$screening->id}/review",
        ['decision' => 'approved']
    )->assertStatus(409);
});

// ── Security: token IDOR closeout ─────────────────────────────────

it('does not expose the upload token in the landlord-facing screening payload', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $lease = LeaseContract::factory()->create(['user_id' => $owner->id]);
    $screening = TenantScreeningRequest::factory()->create([
        'lease_contract_id' => $lease->id,
        'requested_by' => $owner->id,
    ]);

    // SECURITY: the landlord receives this payload (it powers the
    // "review screening" page in their dashboard). The 14-day upload
    // token used to be embedded here; forwarding the page / email
    // leaked it. Verify it never goes back to the wire.
    $response = $this->getJson("/api/v1/my/lease-contracts/{$lease->id}/screening/{$screening->id}")
        ->assertOk();

    expect($response->json('data'))->not->toHaveKey('token');
});

it('does not expose document URLs through the public token endpoint', function (): void {
    $screening = TenantScreeningRequest::factory()->create();
    TenantScreeningDocument::create([
        'screening_request_id' => $screening->id,
        'document_type' => ScreeningDocumentType::IdCard,
        'original_name' => 'carte-id.pdf',
        'disk' => 'local',
        'path' => 'screening/'.$screening->id.'/carte-id.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 12345,
    ]);

    // SECURITY: anyone who learns the 64-char token (forwarded email,
    // screenshot, browser history on a shared computer) used to get
    // signed S3 URLs to the tenant's PII. Public payload now lists
    // metadata only — URLs are gated behind the landlord's
    // authenticated `show()` route.
    $response = $this->getJson("/api/v1/screening/{$screening->token}")
        ->assertOk();

    expect($response->json('data.documents'))->toHaveCount(1);
    expect($response->json('data.documents.0'))->not->toHaveKey('url');
    expect($response->json('data.documents.0.document_type'))->toBe('id_card');
});
