<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TourImageProxyController
{
    /**
     * Stream a tour image from R2 with CORS headers so Pannellum
     * (which fetches images via XHR) can load them from any origin.
     *
     * Route: GET /tour-image/{adId}/{path}
     * The {path} parameter may contain slashes (e.g. tile paths: scenes/x/tiles/1/f0_0.webp)
     */
    public function show(Request $request, string $adId, string $path): StreamedResponse|Response
    {
        // Prevent path traversal — only allow safe path characters and forward slashes.
        if (!preg_match('#^[a-zA-Z0-9\-_.\/]+$#', $path)) {
            return response('Bad Request', 400, [
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        // Resolve the storage path — new structure first, legacy path as fallback.
        $newPath = 'ads/'.$adId.'/tours/'.$path;
        $legacyPath = 'tours/'.$adId.'/'.$path;
        $r2path = Storage::disk()->exists($newPath) ? $newPath : $legacyPath;

        if (!Storage::disk()->exists($r2path)) {
            return response('Not Found', 404, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD',
            ]);
        }

        $mime = Storage::disk()->mimeType($r2path) ?: 'image/webp';
        $size = Storage::disk()->size($r2path);

        return response()->stream(function () use ($r2path): void {
            $stream = Storage::disk()->readStream($r2path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => $size,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD',
        ]);
    }
}
