<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lease;

use App\Enums\ScreeningStatus;
use App\Http\Requests\Api\V1\CreateScreeningRequest;
use App\Http\Requests\Api\V1\ReviewScreeningRequest;
use App\Http\Requests\Api\V1\UploadScreeningDocumentRequest;
use App\Http\Resources\TenantScreeningDocumentResource;
use App\Http\Resources\TenantScreeningRequestResource;
use App\Models\LeaseContract;
use App\Models\TenantScreeningDocument;
use App\Models\TenantScreeningRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TenantScreeningController
{
    // ── Landlord endpoints (auth:sanctum + owner.role) ─────────────

    public function index(Request $request, LeaseContract $leaseContract): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $screenings = $leaseContract->screeningRequests()
            ->with('documents')
            ->latest()
            ->get();

        return response()->json([
            'data' => TenantScreeningRequestResource::collection($screenings),
        ]);
    }

    public function store(CreateScreeningRequest $request, LeaseContract $leaseContract): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validated();
        $expiresInDays = $validated['expires_in_days'] ?? 14;

        $screening = TenantScreeningRequest::create([
            'lease_contract_id' => $leaseContract->id,
            'requested_by' => auth()->id(),
            'tenant_name' => $validated['tenant_name'],
            'tenant_email' => $validated['tenant_email'],
            'token' => Str::random(64),
            'status' => ScreeningStatus::Pending,
            'required_documents' => $validated['required_documents'],
            'landlord_notes' => $validated['landlord_notes'] ?? null,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        return response()->json([
            'data' => new TenantScreeningRequestResource($screening),
        ], 201);
    }

    public function show(Request $request, LeaseContract $leaseContract, TenantScreeningRequest $screening): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($screening->lease_contract_id !== $leaseContract->id) {
            return response()->json(['message' => 'Introuvable'], 404);
        }

        $screening->load('documents');

        return response()->json([
            'data' => new TenantScreeningRequestResource($screening),
        ]);
    }

    public function review(ReviewScreeningRequest $request, LeaseContract $leaseContract, TenantScreeningRequest $screening): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($screening->lease_contract_id !== $leaseContract->id) {
            return response()->json(['message' => 'Introuvable'], 404);
        }

        if ($screening->status !== ScreeningStatus::Submitted) {
            return response()->json([
                'message' => 'Seul un dossier soumis peut être évalué.',
            ], 409);
        }

        $validated = $request->validated();
        $decision = $validated['decision'] === 'approved'
            ? ScreeningStatus::Approved
            : ScreeningStatus::Rejected;

        $screening->update([
            'status' => $decision,
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        $screening->load('documents');

        return response()->json([
            'data' => new TenantScreeningRequestResource($screening),
        ]);
    }

    // ── Public tenant endpoints (token-based, no auth) ────────────

    public function publicShow(string $token): JsonResponse
    {
        $screening = TenantScreeningRequest::where('token', $token)->firstOrFail();

        if ($screening->isExpired() && $screening->status === ScreeningStatus::Pending) {
            $screening->update(['status' => ScreeningStatus::Expired]);
        }

        return response()->json([
            'data' => [
                'tenant_name' => $screening->tenant_name,
                'status' => $screening->status->value,
                'status_label' => $screening->status->getLabel(),
                'required_documents' => $screening->required_documents,
                'landlord_notes' => $screening->landlord_notes,
                'expires_at' => $screening->expires_at->toIso8601String(),
                'documents' => TenantScreeningDocumentResource::collection(
                    $screening->documents
                ),
            ],
        ]);
    }

    public function publicUpload(UploadScreeningDocumentRequest $request, string $token): JsonResponse
    {
        $screening = TenantScreeningRequest::where('token', $token)->firstOrFail();

        if ($screening->status->isTerminal()) {
            return response()->json([
                'message' => 'Ce dossier ne peut plus recevoir de documents.',
            ], 409);
        }

        if ($screening->isExpired()) {
            $screening->update(['status' => ScreeningStatus::Expired]);

            return response()->json(['message' => 'Ce lien a expiré.'], 410);
        }

        $validated = $request->validated();
        $file = $request->file('file');

        $disk = config('filesystems.app_media_disk', 'local');
        $path = $file->store(
            "screening/{$screening->id}",
            $disk
        );

        $document = TenantScreeningDocument::create([
            'screening_request_id' => $screening->id,
            'document_type' => $validated['document_type'],
            'original_name' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'data' => new TenantScreeningDocumentResource($document),
        ], 201);
    }

    public function publicSubmit(string $token): JsonResponse
    {
        $screening = TenantScreeningRequest::where('token', $token)->firstOrFail();

        if ($screening->status->isTerminal()) {
            return response()->json([
                'message' => 'Ce dossier a déjà été finalisé.',
            ], 409);
        }

        if ($screening->isExpired()) {
            $screening->update(['status' => ScreeningStatus::Expired]);

            return response()->json(['message' => 'Ce lien a expiré.'], 410);
        }

        if ($screening->documents()->count() === 0) {
            return response()->json([
                'message' => 'Veuillez téléverser au moins un document avant de soumettre.',
            ], 422);
        }

        $screening->update([
            'status' => ScreeningStatus::Submitted,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'status' => $screening->status->value,
                'status_label' => $screening->status->getLabel(),
                'submitted_at' => $screening->submitted_at->toIso8601String(),
            ],
        ]);
    }
}
