<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\LeaseContract;
use App\Models\LeaseSignatureRequest;
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
            'request' => $requestPayload,
            // Legacy keys (kept for backwards compatibility with mobile clients).
            'data' => $signatureRequest,
            'contract' => $contractPayload,
        ]);
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

        $signatureRequest->forceFill([
            'status' => 'signed',
            'signed_at' => now(),
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

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $signatureRequest->forceFill([
            'status' => 'declined',
            'declined_at' => now(),
            'decline_reason' => $validated['reason'] ?? null,
        ])->save();

        return response()->json(['message' => 'Contrat refusé.']);
    }
}
