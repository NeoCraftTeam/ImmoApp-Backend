<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\LeaseContract;
use App\Models\LeaseSignatureRequest;
use App\Models\User;
use App\Notifications\LeaseSignatureOtpNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Regression coverage for the e-signature security hardening
 * (audit 2026-05-10):
 *
 *   V1 — OTP brute-force lockout after `OTP_MAX_ATTEMPTS` failures.
 *   V2 — PDF anti-substitution: signing is rejected when the contract
 *        PDF was modified after the signature request was created.
 *   V3 — Audit trail (signer IP / User-Agent) captured at sign time.
 *   V5 — `decline()` now refuses an expired request (parity with `sign()`).
 */
function makeSignatureRequest(?string $pdfPath = null, ?string $pdfHash = null): LeaseSignatureRequest
{
    $owner = User::factory()->agents()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);
    // `pdf_path` is NOT NULL — fall back to a placeholder when the test
    // doesn't care about PDF content. `computeContractPdfHash()` returns
    // null when the file is absent, which is exactly the legacy-row code
    // path we want to exercise.
    $lease = LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
        'pdf_path' => $pdfPath ?? 'lease-contracts/placeholder.pdf',
    ]);

    return LeaseSignatureRequest::query()->create([
        'lease_contract_id' => $lease->id,
        'requested_by' => $owner->id,
        'signer_email' => 'tenant@example.com',
        'signer_name' => 'Locataire Test',
        'token' => Str::random(64),
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
        'pdf_hash_at_request' => $pdfHash,
    ]);
}

it('locks the request after OTP_MAX_ATTEMPTS failed sign attempts', function (): void {
    Notification::fake();
    $signature = makeSignatureRequest();

    $this->postJson("/api/v1/signatures/{$signature->token}/send-otp")->assertOk();

    for ($i = 1; $i <= LeaseSignatureRequest::OTP_MAX_ATTEMPTS; $i++) {
        $this->postJson("/api/v1/signatures/{$signature->token}/sign", [
            'otp' => '000000',
        ])->assertUnprocessable();
    }

    $row = LeaseSignatureRequest::query()->where('token', $signature->token)->first();
    expect($row?->status)->toBe('locked');
    expect($row?->sign_otp_hash)->toBeNull();

    // Subsequent attempts should now return 423 Locked instead of 422.
    $this->postJson("/api/v1/signatures/{$signature->token}/sign", ['otp' => '111111'])
        ->assertStatus(423);
});

it('rejects sign when PDF hash changed since request creation', function (): void {
    Notification::fake();
    Storage::fake('public');
    config(['filesystems.app_media_disk' => 'public']);

    Storage::disk('public')->put('lease-contracts/v1.pdf', 'PDF-CONTENT-V1');
    $hashV1 = hash('sha256', 'PDF-CONTENT-V1');

    $signature = makeSignatureRequest('lease-contracts/v1.pdf', $hashV1);

    // Landlord regenerates / tampers the PDF after the link was sent.
    Storage::disk('public')->put('lease-contracts/v1.pdf', 'PDF-CONTENT-V2-TAMPERED');

    $this->postJson("/api/v1/signatures/{$signature->token}/send-otp")->assertOk();

    $otp = null;
    Notification::assertSentOnDemand(LeaseSignatureOtpNotification::class, function (LeaseSignatureOtpNotification $n) use (&$otp): bool {
        $otp = $n->plainCode;

        return true;
    });

    $this->postJson("/api/v1/signatures/{$signature->token}/sign", ['otp' => $otp])
        ->assertStatus(409)
        ->assertJsonFragment(['message' => 'Le contrat a été modifié depuis l’envoi du lien. Demandez un nouveau lien de signature.']);

    expect(LeaseSignatureRequest::query()->where('token', $signature->token)->first()?->status)
        ->toBe('locked');
});

it('captures signer IP and User-Agent on successful sign', function (): void {
    Notification::fake();
    $signature = makeSignatureRequest();

    $this->postJson("/api/v1/signatures/{$signature->token}/send-otp")->assertOk();

    $otp = null;
    Notification::assertSentOnDemand(LeaseSignatureOtpNotification::class, function (LeaseSignatureOtpNotification $n) use (&$otp): bool {
        $otp = $n->plainCode;

        return true;
    });

    $this->withHeaders([
        'User-Agent' => 'KeyHomePestTest/1.0',
        'X-Forwarded-For' => '203.0.113.42',
    ])->postJson("/api/v1/signatures/{$signature->token}/sign", ['otp' => $otp])->assertOk();

    $row = LeaseSignatureRequest::query()->where('token', $signature->token)->first();
    expect($row?->status)->toBe('signed');
    expect($row?->signer_ip)->not->toBeNull();
    expect($row?->signer_user_agent)->toBe('KeyHomePestTest/1.0');
    expect($row?->signature_hash)->toBeNull(); // no PDF in this test → hash null
});

it('refuses to decline an expired signature request', function (): void {
    $signature = makeSignatureRequest();
    $signature->forceFill(['expires_at' => now()->subDay()])->save();

    $this->postJson("/api/v1/signatures/{$signature->token}/decline", ['otp' => '000000'])
        ->assertStatus(410);
});

it('serves an HTML contract preview that renders on iOS', function (): void {
    $signature = makeSignatureRequest();
    $contract = $signature->leaseContract;

    $response = $this->get("/api/v1/signatures/{$signature->token}/preview")
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=utf-8');

    $html = $response->getContent();
    expect(is_string($html) && $html !== '')->toBeTrue();
    expect($html)->toContain('class="lease-contract"')
        ->and($html)->toContain('CONTRAT DE BAIL')
        ->and($html)->toContain($contract->tenant_name)
        ->and($html)->toContain((string) $contract->contract_number);
});

it('never leaks the stored PDF path in the contract preview', function (): void {
    $signature = makeSignatureRequest('lease-contracts/secret-v1.pdf');

    $html = $this->get("/api/v1/signatures/{$signature->token}/preview")
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('lease-contracts/');
});

it('does not flip the request status when previewing (read-only)', function (): void {
    $signature = makeSignatureRequest();

    $this->get("/api/v1/signatures/{$signature->token}/preview")->assertOk();

    expect(LeaseSignatureRequest::query()->where('token', $signature->token)->first()?->status)
        ->toBe('pending');
});

it('returns 404 for an unknown token preview', function (): void {
    $this->get('/api/v1/signatures/does-not-exist/preview')->assertNotFound();
});
