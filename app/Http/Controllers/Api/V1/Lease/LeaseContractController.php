<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lease;

use App\Enums\LeaseAuditEvent;
use App\Enums\LeaseStatus;
use App\Http\Requests\Api\V1\EnhanceLeaseConditionsRequest;
use App\Http\Requests\Api\V1\GenerateLeaseContractRequest;
use App\Http\Requests\Api\V1\RenewLeaseContractRequest;
use App\Http\Requests\Api\V1\SummarizeLeaseContractRequest;
use App\Http\Requests\Api\V1\TerminateLeaseContractRequest;
use App\Http\Requests\Api\V1\UpdateLeaseContractRequest;
use App\Http\Resources\LeaseContractResource;
use App\Models\Ad;
use App\Models\LeaseContract;
use App\Models\LeaseSignatureAuditLog;
use App\Services\Ai\AiDescriptionEnhancer;
use App\Services\Rental\LeaseContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
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

        // A terminated / archived lease is legally frozen: mirror the guard
        // already enforced by renew() and terminate() so no mutation slips
        // through the only edit path that used to lack it.
        if ($leaseContract->status->isTerminal()) {
            return response()->json([
                'message' => 'Un bail résilié ou archivé ne peut plus être modifié.',
            ], 409);
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

        // Record the edit in the tamper-evident trail. Only the field names
        // are stored (never the new values) to keep tenant PII out of the log
        // while still answering "what was changed, by whom, when".
        LeaseSignatureAuditLog::record(
            leaseContractId: $leaseContract->id,
            event: LeaseAuditEvent::Updated,
            userId: auth()->id(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            metadata: ['updated_fields' => array_keys($validated)],
        );

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
    public function summarize(SummarizeLeaseContractRequest $request): JsonResponse
    {
        $data = $request->validated();

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

    /**
     * Renew the lease — extends `lease_end` by N months, optionally
     * updates `monthly_rent`, and resets status to Active so an
     * already-expired lease can be brought back without recreating it.
     *
     * @OA\Post(
     *     path="/api/v1/my/lease-contracts/{leaseContract}/renew",
     *     summary="Renouveler un contrat de bail",
     *     tags={"📄 Contrats de bail"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="leaseContract", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"extend_months"},
     *
     *         @OA\Property(property="extend_months", type="integer", example=12, description="Nombre de mois à ajouter à la fin du bail (1–120)."),
     *         @OA\Property(property="monthly_rent", type="number", example=180000, description="Nouveau loyer mensuel (XAF). Optionnel.")
     *     )),
     *
     *     @OA\Response(response=200, description="Bail renouvelé"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=409, description="Bail résilié ou archivé — renouvellement impossible")
     * )
     */
    public function renew(RenewLeaseContractRequest $request, LeaseContract $leaseContract): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($leaseContract->status->isTerminal()) {
            return response()->json([
                'message' => 'Un bail résilié ou archivé ne peut pas être renouvelé.',
            ], 409);
        }

        $validated = $request->validated();
        $extendMonths = (int) $validated['extend_months'];

        // Anchor renewal off the existing `lease_end` so back-to-back
        // renewals stack cleanly. If the lease has already expired
        // (lease_end < today), anchor off today instead — otherwise the
        // "renewed" lease would already be in the past.
        $today = Carbon::today();
        $anchor = $leaseContract->lease_end && $leaseContract->lease_end->greaterThan($today)
            ? $leaseContract->lease_end->copy()
            : $today;

        // `addMonthsNoOverflow` clamps the result to the source's month
        // end (so Dec 31 + 6 months → Jun 30, not Jul 1) which matches
        // the human expectation of "renew through the end of June".
        $newEnd = $anchor->copy()->addMonthsNoOverflow($extendMonths);

        $updates = [
            'lease_end' => $newEnd->toDateString(),
            'lease_duration_months' => $leaseContract->lease_duration_months + $extendMonths,
            'status' => LeaseStatus::Active->value,
        ];

        if (array_key_exists('monthly_rent', $validated)) {
            $updates['monthly_rent'] = $validated['monthly_rent'];
        }

        $leaseContract->update($updates);

        LeaseSignatureAuditLog::record(
            leaseContractId: $leaseContract->id,
            event: LeaseAuditEvent::Renewed,
            userId: auth()->id(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            metadata: [
                'extend_months' => $extendMonths,
                'new_lease_end' => $newEnd->toDateString(),
                'monthly_rent' => $updates['monthly_rent'] ?? null,
            ],
        );

        return response()->json([
            'success' => true,
            'message' => "Contrat {$leaseContract->contract_number} renouvelé jusqu'au {$newEnd->toDateString()}.",
            'data' => new LeaseContractResource(
                $leaseContract->fresh(['ad', 'ad.media', 'ad.ad_type', 'ad.quarter.city'])
            ),
        ]);
    }

    /**
     * Terminate the lease early. Sets status to Terminated, records the
     * reason, and stamps `terminated_at`. Reversible by archiving and
     * re-generating from the ad if needed.
     *
     * @OA\Post(
     *     path="/api/v1/my/lease-contracts/{leaseContract}/terminate",
     *     summary="Résilier un contrat de bail",
     *     tags={"📄 Contrats de bail"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"reason"},
     *
     *         @OA\Property(property="reason", type="string", example="Départ du locataire après préavis", description="Motif de la résiliation (3–1000 caractères).")
     *     )),
     *
     *     @OA\Response(response=200, description="Bail résilié"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=409, description="Bail déjà résilié ou archivé")
     * )
     */
    public function terminate(TerminateLeaseContractRequest $request, LeaseContract $leaseContract): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($leaseContract->status->isTerminal()) {
            return response()->json([
                'message' => 'Ce bail est déjà résilié ou archivé.',
            ], 409);
        }

        $reason = (string) $request->validated('reason');

        $leaseContract->update([
            'status' => LeaseStatus::Terminated->value,
            'terminated_at' => now(),
            'termination_reason' => $reason,
        ]);

        LeaseSignatureAuditLog::record(
            leaseContractId: $leaseContract->id,
            event: LeaseAuditEvent::Terminated,
            userId: auth()->id(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            metadata: ['reason' => $reason],
        );

        return response()->json([
            'success' => true,
            'message' => "Contrat {$leaseContract->contract_number} résilié.",
            'data' => new LeaseContractResource(
                $leaseContract->fresh(['ad', 'ad.media', 'ad.ad_type', 'ad.quarter.city'])
            ),
        ]);
    }

    /**
     * Archive the lease — hides it from active dashboards while keeping
     * the row for accounting / audit. The lease must already be in a
     * non-Active state (Expired or Terminated) before it can be
     * archived. Archived leases are not deleted and the rent-collection
     * ledger remains queryable via the lease's history.
     *
     * @OA\Post(
     *     path="/api/v1/my/lease-contracts/{leaseContract}/archive",
     *     summary="Archiver un contrat de bail",
     *     tags={"📄 Contrats de bail"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Bail archivé"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=409, description="Bail encore actif — résiliez-le ou attendez son expiration")
     * )
     */
    public function archive(Request $request, LeaseContract $leaseContract): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($leaseContract->status === LeaseStatus::Archived) {
            return response()->json([
                'message' => 'Ce bail est déjà archivé.',
            ], 409);
        }

        if ($leaseContract->status === LeaseStatus::Active || $leaseContract->status === LeaseStatus::Draft) {
            return response()->json([
                'message' => 'Le bail doit être expiré ou résilié avant archivage.',
            ], 409);
        }

        $leaseContract->update([
            'status' => LeaseStatus::Archived->value,
            'archived_at' => now(),
        ]);

        LeaseSignatureAuditLog::record(
            leaseContractId: $leaseContract->id,
            event: LeaseAuditEvent::Archived,
            userId: auth()->id(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'success' => true,
            'message' => "Contrat {$leaseContract->contract_number} archivé.",
            'data' => new LeaseContractResource(
                $leaseContract->fresh(['ad', 'ad.media', 'ad.ad_type', 'ad.quarter.city'])
            ),
        ]);
    }
}
