<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lease;

use App\Http\Requests\Api\V1\StoreTenantRequest;
use App\Http\Requests\Api\V1\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * Tenant management for landlords.
 */
final class TenantController
{
    /**
     * @OA\Get(
     *     path="/api/v1/tenants",
     *     summary="Lister mes locataires",
     *     description="Retourne les locataires enregistrés par le bailleur connecté, avec le nombre de contrats.",
     *     operationId="listTenants",
     *     tags={"👥 Locataires"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Liste paginée de locataires"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(): JsonResponse
    {
        $tenants = Tenant::query()
            ->where('user_id', auth()->id())
            ->withCount('leaseContracts')
            ->latest()
            ->paginate(20);

        return response()->json($tenants);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenants",
     *     summary="Ajouter un locataire",
     *     operationId="storeTenant",
     *     tags={"👥 Locataires"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"first_name","last_name"},
     *
     *         @OA\Property(property="first_name", type="string"),
     *         @OA\Property(property="last_name", type="string"),
     *         @OA\Property(property="email", type="string", format="email", nullable=true),
     *         @OA\Property(property="phone", type="string", nullable=true),
     *         @OA\Property(property="id_number", type="string", nullable=true),
     *         @OA\Property(property="notes", type="string", nullable=true)
     *     )),
     *
     *     @OA\Response(response=201, description="Locataire créé"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['user_id'] = auth()->id();
        $tenant = Tenant::query()->create($validated);

        return response()->json(['data' => $tenant], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenants/{tenant}",
     *     summary="Détail d'un locataire",
     *     operationId="showTenant",
     *     tags={"👥 Locataires"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="tenant", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Locataire avec ses contrats"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=404, description="Locataire introuvable")
     * )
     */
    public function show(Tenant $tenant): JsonResponse
    {
        if ($tenant->user_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $tenant->loadCount('leaseContracts')->load('leaseContracts.ad');

        return response()->json(['data' => $tenant]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/tenants/{tenant}",
     *     summary="Modifier un locataire",
     *     operationId="updateTenant",
     *     tags={"👥 Locataires"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="tenant", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="first_name", type="string"),
     *         @OA\Property(property="last_name", type="string"),
     *         @OA\Property(property="email", type="string", format="email", nullable=true),
     *         @OA\Property(property="phone", type="string", nullable=true),
     *         @OA\Property(property="notes", type="string", nullable=true)
     *     )),
     *
     *     @OA\Response(response=200, description="Locataire mis à jour"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        if ($tenant->user_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $validated = $request->validated();

        $tenant->update($validated);

        return response()->json(['data' => $tenant]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenants/{tenant}",
     *     summary="Supprimer un locataire",
     *     operationId="destroyTenant",
     *     tags={"👥 Locataires"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="tenant", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Locataire supprimé"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        if ($tenant->user_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $tenant->delete();

        return response()->json(['message' => 'Locataire supprimé.']);
    }
}
