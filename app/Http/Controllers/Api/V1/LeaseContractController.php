<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\EnhanceLeaseConditionsRequest;
use App\Http\Requests\Api\V1\GenerateLeaseContractRequest;
use App\Http\Requests\Api\V1\UpdateLeaseContractRequest;
use App\Http\Resources\LeaseContractResource;
use App\Models\Ad;
use App\Models\LeaseContract;
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
            ->with(['ad'])
            ->latest()
            ->paginate(max(1, min(100, (int) $request->input('per_page', 15))));

        return LeaseContractResource::collection($contracts);
    }

    /**
     * Generate a lease contract PDF for the given ad.
     */
    public function show(LeaseContract $leaseContract): LeaseContractResource|JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return new LeaseContractResource($leaseContract->load('ad'));
    }

    public function update(UpdateLeaseContractRequest $request, LeaseContract $leaseContract): LeaseContractResource|JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validated();

        $leaseContract->update($validated);

        return new LeaseContractResource($leaseContract->load('ad'));
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

        return response()->json([
            'success' => true,
            'message' => "Contrat {$contract->contract_number} généré avec succès.",
            'data' => new LeaseContractResource($contract->load('ad')),
        ], 201);
    }

    /**
     * Download the lease contract PDF.
     */
    public function download(LeaseContract $leaseContract): StreamedResponse|JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $disk = config('filesystems.app_media_disk', 'public');
        if (!Storage::disk($disk)->exists($leaseContract->pdf_path)) {
            return response()->json(['message' => 'Fichier introuvable'], 404);
        }

        return response()->streamDownload(
            fn () => print (Storage::disk($disk)->get($leaseContract->pdf_path)),
            "contrat-{$leaseContract->contract_number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }
}
