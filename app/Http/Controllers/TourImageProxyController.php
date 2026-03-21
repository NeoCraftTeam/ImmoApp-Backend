<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\User;
use App\Support\ProxyCorsHeaders;
use App\Support\TourAssetToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TourImageProxyController
{
    /**
     * Stream a tour image from R2 with CORS headers so Photo Sphere Viewer
     * and other XHR-based clients can load them from any origin.
     *
     * Route: GET /tour-image/{adId}/{path}
     * The {path} parameter may contain slashes (e.g. tile paths: scenes/x/tiles/1/f0_0.webp)
     */
    public function show(Request $request, string $adId, string $path): StreamedResponse|Response
    {
        // Support unsaved uploads during owner/agency editing flow.
        // Files are temporarily stored under ads/temp/tours/{filename}.
        if ($adId === 'temp') {
            /** @var User|null $user */
            $user = $request->user();
            if (!$user instanceof User) {
                return response('Forbidden', 403, ProxyCorsHeaders::for($request));
            }

            if (!preg_match('#^[a-zA-Z0-9\-_.\/]+$#', $path)) {
                return response('Bad Request', 400, ProxyCorsHeaders::for($request));
            }

            $tempPath = 'ads/temp/tours/'.$path;
            if (!Storage::disk()->exists($tempPath)) {
                return response('Not Found', 404, ProxyCorsHeaders::for($request));
            }

            $mime = Storage::disk()->mimeType($tempPath) ?: 'image/webp';
            $size = Storage::disk()->size($tempPath);

            return response()->stream(function () use ($tempPath): void {
                $stream = Storage::disk()->readStream($tempPath);
                if ($stream) {
                    fpassthru($stream);
                    fclose($stream);
                }
            }, 200, array_merge([
                'Content-Type' => $mime,
                'Content-Length' => $size,
                'Cache-Control' => 'public, max-age=3600',
            ], ProxyCorsHeaders::for($request)));
        }

        if (!Str::isUuid($adId)) {
            return response('Not Found', 404, ProxyCorsHeaders::for($request));
        }

        $ad = Ad::query()->find($adId);
        if (!$ad || !$ad->has_3d_tour) {
            return response('Not Found', 404, ProxyCorsHeaders::for($request));
        }

        $tokenExp = null;
        $tokenSig = null;
        $normalizedPath = $path;

        if (preg_match('#^__t/(\d+)/([a-f0-9]{64})/(.+)$#', $path, $matches) === 1) {
            $tokenExp = $matches[1];
            $tokenSig = $matches[2];
            $normalizedPath = $matches[3];
        }

        /** @var User|null $user */
        $user = $request->user();
        $hasSessionAccess = $user instanceof User
            && (
                $user->isAdmin()
                || $user->id === $ad->user_id
                || $ad->isUnlockedFor($user)
            );
        $hasTokenAccess = TourAssetToken::validate($adId, $tokenExp, $tokenSig);

        if (!$hasSessionAccess && !$hasTokenAccess) {
            return response('Forbidden', 403, ProxyCorsHeaders::for($request));
        }

        // Prevent path traversal — only allow safe path characters and forward slashes.
        if (!preg_match('#^[a-zA-Z0-9\-_.\/]+$#', $normalizedPath)) {
            return response('Bad Request', 400, ProxyCorsHeaders::for($request));
        }

        // Resolve the storage path — new structure first, legacy path as fallback.
        $disk = Storage::disk(config('filesystems.tour_disk', config('filesystems.default')));
        $newPath = 'ads/'.$adId.'/tours/'.$normalizedPath;
        $legacyPath = 'tours/'.$adId.'/'.$normalizedPath;
        $r2path = $disk->exists($newPath) ? $newPath : $legacyPath;

        if (!$disk->exists($r2path)) {
            return response('Not Found', 404, ProxyCorsHeaders::for($request));
        }

        // Always stream instead of redirecting to R2. The viewer loads images via XHR;
        // a redirect to R2 would return a response without CORS headers, causing
        // "The file could not be accessed" in the hotspot editor.
        $mime = $disk->mimeType($r2path) ?: 'image/webp';
        $size = $disk->size($r2path);

        return response()->stream(function () use ($disk, $r2path): void {
            $stream = $disk->readStream($r2path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, array_merge([
            'Content-Type' => $mime,
            'Content-Length' => $size,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ], ProxyCorsHeaders::for($request)));
    }
}
