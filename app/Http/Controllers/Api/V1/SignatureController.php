<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\LeaseContract;
use App\Models\LeaseSignatureRequest;
use App\Notifications\LeaseSignatureOtpNotification;
use App\Notifications\LeaseSignatureRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

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

    public function store(Request $request, LeaseContract $leaseContract): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $validated = $request->validate([
            'signer_email' => ['required', 'email', 'max:255'],
            'signer_name' => ['required', 'string', 'max:255'],
        ]);

        $signatureRequest = LeaseSignatureRequest::query()->create([
            'lease_contract_id' => $leaseContract->id,
            'requested_by' => auth()->id(),
            'signer_email' => $validated['signer_email'],
            'signer_name' => $validated['signer_name'],
            'token' => Str::random(64),
            'status' => 'pending',
            'expires_at' => now()->addDays(30),
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

    public function sendSignOtp(string $token): JsonResponse
    {
        $signatureRequest = LeaseSignatureRequest::query()
            ->where('token', $token)
            ->firstOrFail();

        if (!$signatureRequest->isPending() && $signatureRequest->status !== 'viewed') {
            return response()->json(['message' => 'Cette demande ne peut pas recevoir de code.'], 409);
        }

        if ($signatureRequest->isExpired()) {
            return response()->json(['message' => 'Cette demande de signature a expiré.'], 410);
        }

        $plain = sprintf('%06d', random_int(0, 999_999));
        $hash = hash_hmac('sha256', $plain, (string) config('app.key'));

        $signatureRequest->forceFill([
            'sign_otp_hash' => $hash,
            'sign_otp_expires_at' => now()->addMinutes(15),
            'sign_otp_expires_unix' => now()->addMinutes(15)->getTimestamp(),
            'sign_otp_sent_at' => now(),
        ])->save();

        Notification::route('mail', $signatureRequest->signer_email)
            ->notify(new LeaseSignatureOtpNotification($signatureRequest, $plain));

        return response()->json(['message' => 'Code envoyé par e-mail.']);
    }

    public function sign(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'max:32'],
        ]);

        $signatureRequest = LeaseSignatureRequest::query()
            ->where('token', $token)
            ->firstOrFail();

        if (!$this->otpMatches($signatureRequest, $validated['otp'])) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        if (!$signatureRequest->isPending() && $signatureRequest->status !== 'viewed') {
            return response()->json(['message' => 'Cette demande ne peut pas être signée.'], 409);
        }

        if ($signatureRequest->isExpired()) {
            return response()->json(['message' => 'Cette demande de signature a expiré.'], 410);
        }

        $signatureRequest->forceFill([
            'status' => 'signed',
            'signed_at' => now(),
            'sign_otp_hash' => null,
            'sign_otp_expires_at' => null,
            'sign_otp_expires_unix' => null,
        ])->save();

        return response()->json(['message' => 'Contrat signé avec succès.']);
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $signatureRequest = LeaseSignatureRequest::query()
            ->where('token', $token)
            ->firstOrFail();

        if (!$this->otpMatches($signatureRequest, $validated['otp'])) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        if (!$signatureRequest->isPending() && $signatureRequest->status !== 'viewed') {
            return response()->json(['message' => 'Cette demande ne peut pas être refusée.'], 409);
        }

        $signatureRequest->forceFill([
            'status' => 'declined',
            'declined_at' => now(),
            'decline_reason' => $validated['reason'] ?? null,
            'sign_otp_hash' => null,
            'sign_otp_expires_at' => null,
            'sign_otp_expires_unix' => null,
        ])->save();

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
}
