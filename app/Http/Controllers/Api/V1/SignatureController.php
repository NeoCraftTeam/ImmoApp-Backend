<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\LeaseContract;
use App\Models\LeaseSignatureRequest;
use App\Notifications\LeaseSignatureOtpNotification;
use App\Notifications\LeaseSignatureRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
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

        $contractPayload = [
            'tenant_name' => $contract->tenant_name,
            'monthly_rent' => $contract->monthly_rent,
            'lease_start' => $contract->lease_start,
            'lease_end' => $contract->lease_end,
            'contract_number' => $contract->contract_number,
        ];

        $requestPayload = $signatureRequest->toArray();
        $requestPayload['contract'] = $contractPayload;

        $otpActionsAllowed = !$signatureRequest->isExpired()
            && ($signatureRequest->isPending() || $signatureRequest->status === 'viewed');

        return response()->json([
            'request' => $requestPayload,
            'data' => $signatureRequest,
            'contract' => $contractPayload,
            'security' => [
                'otp_required_for_sign_or_decline' => $otpActionsAllowed,
            ],
        ]);
    }

    /**
     * Send or refresh a one-time code to the signer's email. Required before sign/decline.
     */
    public function sendSignOtp(Request $request, string $token): JsonResponse
    {
        $signatureRequest = LeaseSignatureRequest::query()
            ->where('token', $token)
            ->firstOrFail();

        if ($signatureRequest->isExpired()) {
            return response()->json(['message' => 'Cette demande de signature a expiré.'], 410);
        }

        if (!$signatureRequest->isPending() && $signatureRequest->status !== 'viewed') {
            return response()->json(['message' => 'Aucun code requis pour cette demande.'], 409);
        }

        $cooldownKey = 'lease-sig-otp-cooldown:'.$token;
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            return response()->json([
                'message' => 'Merci d\'attendre avant de demander un nouveau code.',
                'retry_after' => RateLimiter::availableIn($cooldownKey),
            ], 429);
        }

        $hourKey = 'lease-sig-otp-hour:'.$token;
        if (RateLimiter::tooManyAttempts($hourKey, 10)) {
            return response()->json(['message' => 'Trop de demandes de code. Réessayez plus tard.'], 429);
        }

        $plain = (string) random_int(100000, 999999);
        $hash = hash('sha256', $plain.config('app.key').$token);

        Cache::put('lease-sig-otp-active:'.$token, true, now()->addMinutes(15));

        $signatureRequest->forceFill([
            'sign_otp_hash' => $hash,
            'sign_otp_expires_at' => now()->addMinutes(15),
            'sign_otp_sent_at' => now(),
        ])->save();

        RateLimiter::hit($cooldownKey, 60);
        RateLimiter::hit($hourKey, 3600);

        Notification::route('mail', $signatureRequest->signer_email)
            ->notify(new LeaseSignatureOtpNotification($signatureRequest, $plain));

        return response()->json(['message' => 'Un code à 6 chiffres a été envoyé par e-mail.']);
    }

    public function sign(Request $request, string $token): JsonResponse
    {
        $signatureRequest = LeaseSignatureRequest::query()
            ->where('token', $token)
            ->firstOrFail();

        if (!$signatureRequest->isPending() && $signatureRequest->status !== 'viewed') {
            return response()->json(['message' => 'Cette demande ne peut pas être signée.'], 409);
        }

        if ($signatureRequest->isExpired()) {
            return response()->json(['message' => 'Cette demande de signature a expiré.'], 410);
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        $failKey = 'lease-sig-otp-fail:'.$token;
        if (RateLimiter::tooManyAttempts($failKey, 5)) {
            return response()->json(['message' => 'Trop de tentatives incorrectes. Demandez un nouveau code.'], 429);
        }

        if (!Cache::has('lease-sig-otp-active:'.$token) || $signatureRequest->sign_otp_hash === null) {
            return response()->json(['message' => 'Code expiré ou manquant. Demandez un nouveau code.'], 422);
        }

        $expected = hash('sha256', $validated['otp'].config('app.key').$token);
        if (!hash_equals((string) $signatureRequest->sign_otp_hash, $expected)) {
            RateLimiter::hit($failKey, 900);

            return response()->json(['message' => 'Code incorrect.'], 422);
        }

        RateLimiter::clear($failKey);
        Cache::forget('lease-sig-otp-active:'.$token);

        $signatureRequest->forceFill([
            'status' => 'signed',
            'signed_at' => now(),
            'sign_otp_hash' => null,
            'sign_otp_expires_at' => null,
            'sign_otp_sent_at' => null,
        ])->save();

        return response()->json(['message' => 'Contrat signé avec succès.']);
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        $signatureRequest = LeaseSignatureRequest::query()
            ->where('token', $token)
            ->firstOrFail();

        if (!$signatureRequest->isPending() && $signatureRequest->status !== 'viewed') {
            return response()->json(['message' => 'Cette demande ne peut pas être refusée.'], 409);
        }

        if ($signatureRequest->isExpired()) {
            return response()->json(['message' => 'Cette demande de signature a expiré.'], 410);
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $failKey = 'lease-sig-otp-fail:'.$token;
        if (RateLimiter::tooManyAttempts($failKey, 5)) {
            return response()->json(['message' => 'Trop de tentatives incorrectes. Demandez un nouveau code.'], 429);
        }

        if (!Cache::has('lease-sig-otp-active:'.$token) || $signatureRequest->sign_otp_hash === null) {
            return response()->json(['message' => 'Code expiré ou manquant. Demandez un nouveau code.'], 422);
        }

        $expected = hash('sha256', $validated['otp'].config('app.key').$token);
        if (!hash_equals((string) $signatureRequest->sign_otp_hash, $expected)) {
            RateLimiter::hit($failKey, 900);

            return response()->json(['message' => 'Code incorrect.'], 422);
        }

        RateLimiter::clear($failKey);
        Cache::forget('lease-sig-otp-active:'.$token);

        $signatureRequest->forceFill([
            'status' => 'declined',
            'declined_at' => now(),
            'decline_reason' => $validated['reason'] ?? null,
            'sign_otp_hash' => null,
            'sign_otp_expires_at' => null,
            'sign_otp_sent_at' => null,
        ])->save();

        return response()->json(['message' => 'Contrat refusé.']);
    }
}
