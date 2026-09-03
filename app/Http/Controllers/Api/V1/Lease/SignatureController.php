<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lease;

use App\Http\Requests\Api\V1\DeclineSignatureRequest;
use App\Http\Requests\Api\V1\SignContractRequest;
use App\Http\Requests\Api\V1\StoreSignatureRequest;
use App\Models\LeaseContract;
use App\Models\LeaseSignatureRequest;
use App\Notifications\LeaseSignatureOtpNotification;
use App\Notifications\LeaseSignatureRequestNotification;
use App\Services\Rental\LeaseContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class SignatureController
{
    public function index(LeaseContract $leaseContract): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $signatures = LeaseSignatureRequest::query()
            ->where('lease_contract_id', $leaseContract->id)
            ->latest()
            ->get();

        return response()->json(['data' => $signatures]);
    }

    public function store(StoreSignatureRequest $request, LeaseContract $leaseContract): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $validated = $request->validated();

        // PDF anti-substitution binding: snapshot the SHA-256 of the contract
        // PDF at request time. `sign()` rejects the request if the current PDF
        // hash differs — i.e. the landlord regenerated/edited the contract
        // between the request and the tenant clicking « Sign ».
        $pdfHash = $this->computeContractPdfHash($leaseContract);

        $signatureRequest = LeaseSignatureRequest::query()->create([
            'lease_contract_id' => $leaseContract->id,
            'requested_by' => auth()->id(),
            'signer_email' => $validated['signer_email'],
            'signer_name' => $validated['signer_name'],
            'token' => Str::random(64),
            'status' => 'pending',
            'expires_at' => now()->addDays(30),
            'pdf_hash_at_request' => $pdfHash,
        ]);

        Log::info('signature.request.created', [
            'signature_id' => $signatureRequest->id,
            'lease_contract_id' => $leaseContract->id,
            'requested_by' => auth()->id(),
            'ip' => $request->ip(),
            'pdf_bound' => $pdfHash !== null,
        ]);

        Notification::route('mail', $validated['signer_email'])
            ->notify(new LeaseSignatureRequestNotification($signatureRequest));

        return response()->json(['data' => $signatureRequest], 201);
    }

    public function show(string $token): JsonResponse
    {
        $signatureRequest = LeaseSignatureRequest::query()
            ->where('token', $token)
            ->with('leaseContract')
            ->firstOrFail();

        if ($signatureRequest->isPending()) {
            $signatureRequest->forceFill([
                'status' => 'viewed',
                'viewed_at' => now(),
            ])->save();
        }

        $contract = $signatureRequest->leaseContract;

        // Frontend `/sign/[token]` consumes `data.request` with `contract`
        // nested inside it. Returning a flat `{ data, contract }` shape made
        // the page show "Lien invalide ou expiré" because `data.request` was
        // always undefined. Keep the legacy keys in the response for any
        // pre-existing consumer, but expose the new canonical envelope too.
        $contractPayload = [
            'tenant_name' => $contract->tenant_name,
            'monthly_rent' => $contract->monthly_rent,
            'lease_start' => $contract->lease_start,
            'lease_end' => $contract->lease_end,
            'contract_number' => $contract->contract_number,
        ];

        $requestPayload = $signatureRequest->toArray();
        $requestPayload['contract'] = $contractPayload;

        return response()->json([
            'security' => [
                'otp_required_for_sign_or_decline' => true,
            ],
            'request' => $requestPayload,
            // Legacy keys (kept for backwards compatibility with mobile clients).
            'data' => $signatureRequest,
            'contract' => $contractPayload,
        ]);
    }

    /**
     * Public, token-scoped HTML preview of the contract being signed.
     *
     * Served as HTML (never the stored PDF blob) so it renders on iOS Safari /
     * WebKit, where a `blob:` PDF inside an iframe shows blank. Read-only: it
     * never flips the request status — that stays {@see show()}'s job — and it
     * stays available whatever the status so a signer can re-read what they
     * signed. Renders the same payload the printable PDF uses, so the on-screen
     * document matches the file the anti-substitution hash binds to.
     */
    public function preview(string $token): SymfonyResponse
    {
        $signatureRequest = LeaseSignatureRequest::query()
            ->where('token', $token)
            ->with([
                'leaseContract.ad.ad_type',
                'leaseContract.ad.quarter.city',
                'leaseContract.user',
            ])
            ->firstOrFail();

        $data = app(LeaseContractService::class)->pdfViewData($signatureRequest->leaseContract);
        if ($data === null) {
            abort(404);
        }

        $html = view('pdf.lease-contract-preview', $data)->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    public function sendSignOtp(Request $request, string $token): JsonResponse
    {
        $signatureRequest = LeaseSignatureRequest::query()
            ->where('token', $token)
            ->firstOrFail();

        if ($signatureRequest->isLocked()) {
            return response()->json(['message' => 'Cette demande a été verrouillée pour des raisons de sécurité.'], 423);
        }

        if (!$signatureRequest->isPending() && $signatureRequest->status !== 'viewed') {
            return response()->json(['message' => 'Cette demande ne peut pas recevoir de code.'], 409);
        }

        if ($signatureRequest->isExpired()) {
            return response()->json(['message' => 'Cette demande de signature a expiré.'], 410);
        }

        // Per-token cap: the IP throttle already limits brute force on the
        // public route, but a determined attacker behind a botnet could spam
        // OTP issuance to flood the signer's mailbox. Count successful issues
        // via `sign_otp_attempts` baseline + a dedicated counter.
        $issued = (int) ($signatureRequest->sign_otp_attempts ?? 0);
        if ($issued >= LeaseSignatureRequest::OTP_MAX_ISSUES) {
            Log::warning('signature.otp.issue_cap_reached', [
                'signature_id' => $signatureRequest->id,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Trop de demandes de code. Contactez l’émetteur du contrat.'], 429);
        }

        $plain = sprintf('%06d', random_int(0, 999_999));
        $hash = hash_hmac('sha256', $plain, (string) config('app.key'));

        $signatureRequest->forceFill([
            'sign_otp_hash' => $hash,
            'sign_otp_expires_at' => now()->addMinutes(15),
            'sign_otp_expires_unix' => now()->addMinutes(15)->getTimestamp(),
            'sign_otp_sent_at' => now(),
            // Reset failed-attempt counter on each new OTP — preserves the
            // lockout semantic while letting a legitimate signer recover
            // from a typo by requesting a fresh code.
            'sign_otp_attempts' => 0,
        ])->save();

        Log::info('signature.otp.sent', [
            'signature_id' => $signatureRequest->id,
            'ip' => $request->ip(),
        ]);

        Notification::route('mail', $signatureRequest->signer_email)
            ->notify(new LeaseSignatureOtpNotification($signatureRequest, $plain));

        return response()->json(['message' => 'Code envoyé par e-mail.']);
    }

    public function sign(SignContractRequest $request, string $token): JsonResponse
    {
        $validated = $request->validated();

        $signatureRequest = LeaseSignatureRequest::query()
            ->where('token', $token)
            ->with('leaseContract')
            ->firstOrFail();

        // Order matters: state checks BEFORE OTP comparison so a locked /
        // expired / already-signed request returns the right status code
        // instead of leaking « bad OTP » when the real reason is different.
        if ($signatureRequest->isLocked()) {
            return response()->json(['message' => 'Cette demande a été verrouillée après trop de tentatives.'], 423);
        }

        if (!$signatureRequest->isPending() && $signatureRequest->status !== 'viewed') {
            return response()->json(['message' => 'Cette demande ne peut pas être signée.'], 409);
        }

        if ($signatureRequest->isExpired()) {
            return response()->json(['message' => 'Cette demande de signature a expiré.'], 410);
        }

        if (!$this->otpMatches($signatureRequest, $validated['otp'])) {
            $this->registerOtpFailure($signatureRequest, $request, 'sign');

            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        // Anti-substitution: the landlord can `regeneratePdf()` at any time.
        // We refuse to bind a signature to a PDF the signer never saw.
        if (!$this->pdfHashMatchesBinding($signatureRequest)) {
            Log::critical('signature.pdf_mismatch', [
                'signature_id' => $signatureRequest->id,
                'lease_contract_id' => $signatureRequest->lease_contract_id,
                'ip' => $request->ip(),
            ]);
            $signatureRequest->forceFill(['status' => 'locked'])->save();

            return response()->json([
                'message' => 'Le contrat a été modifié depuis l’envoi du lien. Demandez un nouveau lien de signature.',
            ], 409);
        }

        $currentPdfHash = $this->computeContractPdfHash($signatureRequest->leaseContract);

        $signatureRequest->forceFill([
            'status' => 'signed',
            'signed_at' => now(),
            'signature_hash' => $currentPdfHash,
            'signer_ip' => $request->ip(),
            'signer_user_agent' => substr((string) $request->userAgent(), 0, 512),
            'sign_otp_hash' => null,
            'sign_otp_expires_at' => null,
            'sign_otp_expires_unix' => null,
        ])->save();

        Log::info('signature.signed', [
            'signature_id' => $signatureRequest->id,
            'lease_contract_id' => $signatureRequest->lease_contract_id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'pdf_hash' => $currentPdfHash,
        ]);

        return response()->json(['message' => 'Contrat signé avec succès.']);
    }

    public function decline(DeclineSignatureRequest $request, string $token): JsonResponse
    {
        $validated = $request->validated();

        $signatureRequest = LeaseSignatureRequest::query()
            ->where('token', $token)
            ->firstOrFail();

        if ($signatureRequest->isLocked()) {
            return response()->json(['message' => 'Cette demande a été verrouillée après trop de tentatives.'], 423);
        }

        if (!$signatureRequest->isPending() && $signatureRequest->status !== 'viewed') {
            return response()->json(['message' => 'Cette demande ne peut pas être refusée.'], 409);
        }

        if ($signatureRequest->isExpired()) {
            return response()->json(['message' => 'Cette demande de signature a expiré.'], 410);
        }

        if (!$this->otpMatches($signatureRequest, $validated['otp'])) {
            $this->registerOtpFailure($signatureRequest, $request, 'decline');

            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        $signatureRequest->forceFill([
            'status' => 'declined',
            'declined_at' => now(),
            'decline_reason' => $validated['reason'] ?? null,
            'signer_ip' => $request->ip(),
            'signer_user_agent' => substr((string) $request->userAgent(), 0, 512),
            'sign_otp_hash' => null,
            'sign_otp_expires_at' => null,
            'sign_otp_expires_unix' => null,
        ])->save();

        Log::info('signature.declined', [
            'signature_id' => $signatureRequest->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'Contrat refusé.']);
    }

    private function otpMatches(LeaseSignatureRequest $signatureRequest, string $otp): bool
    {
        $stored = $signatureRequest->sign_otp_hash;
        if ($stored === null || $stored === '') {
            return false;
        }

        if (
            $signatureRequest->sign_otp_expires_unix !== null
            && now()->getTimestamp() > $signatureRequest->sign_otp_expires_unix
        ) {
            return false;
        }

        if (
            $signatureRequest->sign_otp_expires_unix === null
            && (
                $signatureRequest->sign_otp_expires_at === null
                || $signatureRequest->sign_otp_expires_at->isPast()
            )
        ) {
            return false;
        }

        $normalized = $this->normalizeSignOtp($otp);
        $hash = hash_hmac('sha256', $normalized, (string) config('app.key'));

        return hash_equals((string) $stored, $hash);
    }

    /**
     * Accept 6-digit OTPs whether JSON decoded them as int (leading zeros lost)
     * or string.
     */
    private function normalizeSignOtp(string $otp): string
    {
        $digits = preg_replace('/\D+/', '', $otp) ?? '';

        return str_pad($digits, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Bump the failed-attempt counter and lock the request once the cap is
     * reached. A locked request can only be unblocked by the landlord re-
     * issuing a fresh signature request (new token), which is the desired
     * UX after suspected brute force.
     */
    private function registerOtpFailure(LeaseSignatureRequest $signatureRequest, Request $request, string $action): void
    {
        $attempts = (int) ($signatureRequest->sign_otp_attempts ?? 0) + 1;

        $updates = ['sign_otp_attempts' => $attempts];
        $reachedCap = $attempts >= LeaseSignatureRequest::OTP_MAX_ATTEMPTS;
        if ($reachedCap) {
            $updates['status'] = 'locked';
            $updates['sign_otp_hash'] = null;
            $updates['sign_otp_expires_at'] = null;
            $updates['sign_otp_expires_unix'] = null;
        }

        $signatureRequest->forceFill($updates)->save();

        Log::warning('signature.otp.failed', [
            'signature_id' => $signatureRequest->id,
            'action' => $action,
            'attempts' => $attempts,
            'locked' => $reachedCap,
            'ip' => $request->ip(),
        ]);
    }

    /**
     * Compute SHA-256 of the current contract PDF. Returns null when the file
     * is unavailable (legacy contracts without a stored PDF) — `sign()`
     * treats that as « no binding to enforce » to preserve backwards
     * compatibility, while `store()` simply records null so future sign
     * attempts skip the check rather than locking everyone out.
     */
    private function computeContractPdfHash(LeaseContract $contract): ?string
    {
        $path = $contract->pdf_path;
        if (!$path) {
            return null;
        }

        $disk = config('filesystems.app_media_disk', 'public');
        try {
            if (!Storage::disk($disk)->exists($path)) {
                return null;
            }
            $contents = Storage::disk($disk)->get($path);
        } catch (\Throwable $e) {
            Log::warning('signature.pdf_hash_failed', [
                'lease_contract_id' => $contract->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $contents === null ? null : hash('sha256', $contents);
    }

    /**
     * Match the live contract PDF against the hash captured at request
     * creation. Returns true when there's no binding to enforce (legacy
     * rows) so we don't lock historical contracts.
     */
    private function pdfHashMatchesBinding(LeaseSignatureRequest $signatureRequest): bool
    {
        $bound = $signatureRequest->pdf_hash_at_request;
        if (!$bound) {
            return true;
        }

        $current = $this->computeContractPdfHash($signatureRequest->leaseContract);
        if ($current === null) {
            return true;
        }

        return hash_equals((string) $bound, $current);
    }
}
