<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTenantRequest;
use App\Http\Requests\Api\V1\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * Tenant management for landlords.
 */
final class TenantController
{
    public function index(): JsonResponse
    {
        $tenants = Tenant::query()
            ->where('user_id', auth()->id())
            ->withCount('leaseContracts')
            ->latest()
            ->paginate(20);

        return response()->json($tenants);
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['user_id'] = auth()->id();
        $tenant = Tenant::query()->create($validated);

        return response()->json(['data' => $tenant], 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        if ($tenant->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $tenant->loadCount('leaseContracts')->load('leaseContracts.ad');

        return response()->json(['data' => $tenant]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        if ($tenant->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validated();

        $tenant->update($validated);

        return response()->json(['data' => $tenant]);
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        if ($tenant->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $tenant->delete();

        return response()->json(['message' => 'Locataire supprimé.']);
    }
}
