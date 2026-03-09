<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaProxyController
{
    /**
     * Stream a Spatie Media Library file from R2 so Filament's FilePond
     * can display previews even when the bucket is not publicly accessible.
     *
     * Route: GET /media-proxy/{uuid}
     */
    public function show(Request $request, string $uuid): StreamedResponse|Response
    {
        // Only allow valid UUID characters.
        if (!preg_match('/^[0-9a-f\-]+$/i', $uuid)) {
            return response('Bad Request', 400);
        }

        $media = Media::where('uuid', $uuid)->first();

        if (!$media) {
            return response('Not Found', 404);
        }

        $disk = $media->disk ?? config('media-library.disk_name', 'r2');
        $path = $media->getPath();

        if (!Storage::disk($disk)->exists($path)) {
            return response('Not Found', 404);
        }

        $mime = $media->mime_type ?: (Storage::disk($disk)->mimeType($path) ?: 'image/webp');
        $size = $media->size ?: Storage::disk($disk)->size($path);

        return response()->stream(function () use ($disk, $path): void {
            $stream = Storage::disk($disk)->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => $size,
            'Cache-Control' => 'private, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD',
        ]);
    }
}
