<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreDocumentRequest;
use App\Models\Ad;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Property document management (permits, insurance, titles, etc.)
 */
final class DocumentController
{
    public function index(Ad $ad): JsonResponse
    {
        if ($ad->user_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $type = request()->query('type');

        $documents = Document::query()
            ->where('ad_id', $ad->id)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->latest()
            ->get();

        return response()->json(['data' => $documents]);
    }

    public function store(StoreDocumentRequest $request, Ad $ad): JsonResponse
    {
        if ($ad->user_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $validated = $request->validated();

        $file = $request->file('file');
        $path = $file->store('documents/'.auth()->id(), 'private');

        $document = Document::query()->create([
            'ad_id' => $ad->id,
            'user_id' => auth()->id(),
            'type' => $validated['type'],
            'name' => $validated['name'],
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return response()->json(['data' => $document], 201);
    }

    public function download(Document $document): mixed
    {
        if ($document->user_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        return Storage::disk('private')->download($document->file_path, $document->name);
    }

    public function destroy(Document $document): JsonResponse
    {
        if ($document->user_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        Storage::disk('private')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Document supprimé.']);
    }
}
