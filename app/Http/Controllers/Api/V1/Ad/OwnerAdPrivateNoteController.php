<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ad;

use App\Models\Ad;
use App\Models\OwnerAdPrivateNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OwnerAdPrivateNoteController
{
    public function show(Request $request, Ad $ad): JsonResponse
    {
        $this->assertOwner($request, $ad);
        $note = OwnerAdPrivateNote::query()
            ->whereBelongsTo($ad)
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json(['data' => $note ? $this->payload($note) : null]);
    }

    public function update(Request $request, Ad $ad): JsonResponse
    {
        $this->assertOwner($request, $ad);
        $validated = $request->validate([
            'is_property_owner' => ['required', 'boolean'],
            'owner_name' => ['nullable', 'required_if:is_property_owner,false', 'string', 'max:150'],
            'owner_address' => ['nullable', 'required_if:is_property_owner,false', 'string', 'max:500'],
            'owner_phone' => ['nullable', 'required_if:is_property_owner,false', 'string', 'max:40'],
            'owner_email' => ['nullable', 'email:rfc', 'max:254'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        if ($validated['is_property_owner']) {
            $validated = [...$validated, ...array_fill_keys([
                'owner_name', 'owner_address', 'owner_phone', 'owner_email', 'notes',
            ], null)];
        }

        $note = OwnerAdPrivateNote::query()->updateOrCreate(
            ['ad_id' => $ad->id, 'user_id' => $request->user()->id],
            $validated,
        );

        return response()->json(['data' => $this->payload($note)]);
    }

    public function destroy(Request $request, Ad $ad): JsonResponse
    {
        $this->assertOwner($request, $ad);
        OwnerAdPrivateNote::query()
            ->whereBelongsTo($ad)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(status: 204);
    }

    private function assertOwner(Request $request, Ad $ad): void
    {
        if (!$request->user() || $request->user()->id !== $ad->user_id) {
            abort(404);
        }
    }

    /** @return array<string, mixed> */
    private function payload(OwnerAdPrivateNote $note): array
    {
        return [
            'is_property_owner' => $note->is_property_owner,
            'owner_name' => $note->owner_name,
            'owner_address' => $note->owner_address,
            'owner_phone' => $note->owner_phone,
            'owner_email' => $note->owner_email,
            'notes' => $note->notes,
            'updated_at' => $note->updated_at?->toISOString(),
        ];
    }
}
