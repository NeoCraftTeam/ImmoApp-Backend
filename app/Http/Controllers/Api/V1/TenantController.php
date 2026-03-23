<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

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

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        if ($tenant->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

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
