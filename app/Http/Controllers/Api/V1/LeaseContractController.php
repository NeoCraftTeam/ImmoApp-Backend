<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeaseAuditEvent;
use App\Http\Requests\Api\V1\EnhanceLeaseConditionsRequest;
use App\Http\Requests\Api\V1\GenerateLeaseContractRequest;
use App\Http\Requests\Api\V1\UpdateLeaseContractRequest;
use App\Http\Resources\LeaseContractResource;
use App\Models\Ad;
use App\Models\LeaseContract;
use App\Models\LeaseSignatureAuditLog;
use App\Services\AiDescriptionEnhancer;
use App\Services\LeaseContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LeaseContractController
{
    /**
     * List all lease contracts owned by the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/lease-contracts",
     *     summary="Lister mes contrats de bail",
     *     description="Retourne la liste paginée des contrats de bail de l'utilisateur authentifié.",
     *     tags={"📄 Contrats de bail"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Liste des contrats"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $contracts = LeaseContract::query()
            ->where('user_id', auth()->id())
            ->with(['ad', 'ad.media', 'ad.ad_type', 'ad.quarter.city'])
            ->latest()
            ->paginate(max(1, min(100, (int) $request->input('per_page', 15))));

        return LeaseContractResource::collection($contracts);
    }

    /**
     * Generate a lease contract PDF for the given ad.
     */
    public function show(Request $request, LeaseContract $leaseContract): LeaseContractResource|JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        LeaseSignatureAuditLog::record(
            leaseContractId: $leaseContract->id,
            event: LeaseAuditEvent::Viewed,
            userId: auth()->id(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return new LeaseContractResource($leaseContract->load(['ad', 'ad.media', 'ad.ad_type', 'ad.quarter.city']));
    }

    public function update(UpdateLeaseContractRequest $request, LeaseContract $leaseContract): LeaseContractResource|JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validated();
        $leaseContract->update($validated);

        // Editable fields on the contract (rent / dates / charges / parties)
        // affect the PDF body; regenerate so the downloadable file matches the
        // displayed data. Best-effort: failure to render falls back silently
        // to the previous PDF instead of breaking the API contract.
        try {
            $leaseContract->load(['user', 'ad.ad_type', 'ad.quarter.city']);
            app(LeaseContractService::class)->regeneratePdf($leaseContract);
        } catch (\Throwable $e) {
            report($e);
        }

        return new LeaseContractResource(
            $leaseContract->fresh(['ad', 'ad.media', 'ad.ad_type', 'ad.quarter.city'])
        );
    }

    /**
     * Enhance lease contract special conditions using AI.
     */
    public function enhanceConditions(EnhanceLeaseConditionsRequest $request): JsonResponse
    {
        $enhanced = app(AiDescriptionEnhancer::class)->enhanceLeaseConditions(
            $request->validated('conditions')
        );

        return response()->json(['enhanced' => $enhanced]);
    }

    /**
     * Summarize a lease contract in plain language for the tenant.
     */
    public function summarize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'monthly_rent' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'string', 'max:30'],
            'duration_months' => ['nullable', 'integer', 'min:1'],
            'special_conditions' => ['nullable', 'string', 'max:5000'],
        ]);

        $summary = app(AiDescriptionEnhancer::class)->summarizeLeaseContract(
            array_filter($data, fn ($v) => $v !== null)
        );

        return response()->json(['summary' => $summary]);
    }

    public function store(GenerateLeaseContractRequest $request, Ad $ad): JsonResponse
    {
        if ($ad->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $ad->load(['ad_type', 'quarter.city']);

        $contract = app(LeaseContractService::class)->generate(
            $ad,
            $request->user(),
            $request->validated(),
        );

        LeaseSignatureAuditLog::record(
            leaseContractId: $contract->id,
            event: LeaseAuditEvent::Generated,
            userId: auth()->id(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            metadata: ['contract_number' => $contract->contract_number],
        );

        return response()->json([
            'success' => true,
            'message' => "Contrat {$contract->contract_number} généré avec succès.",
            'data' => new LeaseContractResource($contract->load('ad')),
        ], 201);
    }

    /**
     * Download the lease contract PDF.
     */
    public function download(Request $request, LeaseContract $leaseContract): StreamedResponse|JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $disk = config('filesystems.app_media_disk', 'public');
        if (!Storage::disk($disk)->exists($leaseContract->pdf_path)) {
            return response()->json(['message' => 'Fichier introuvable'], 404);
        }

        LeaseSignatureAuditLog::record(
            leaseContractId: $leaseContract->id,
            event: LeaseAuditEvent::Downloaded,
            userId: auth()->id(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->streamDownload(
            fn () => print (Storage::disk($disk)->get($leaseContract->pdf_path)),
            "contrat-{$leaseContract->contract_number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Return the audit trail for a lease contract (owner only).
     *
     * @OA\Get(
     *     path="/api/v1/lease-contracts/{leaseContract}/audit-log",
     *     summary="Journal d'audit d'un contrat de bail",
     *     description="Retourne le journal chronologique des événements du contrat : génération, consultation, téléchargement, signature.",
     *     tags={"📄 Contrats de bail"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="leaseContract", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Journal d'audit"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=404, description="Contrat introuvable")
     * )
     */
    public function auditLog(LeaseContract $leaseContract): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $logs = LeaseSignatureAuditLog::query()
            ->where('lease_contract_id', $leaseContract->id)
            ->with('user:id,firstname,lastname,email')
            ->orderBy('occurred_at')
            ->get(['id', 'lease_contract_id', 'user_id', 'event', 'ip_address', 'metadata', 'occurred_at']);

        return response()->json(['data' => $logs]);
    }
}
