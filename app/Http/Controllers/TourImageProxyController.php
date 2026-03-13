<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\User;
use App\Support\TourAssetToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        // Support unsaved uploads during owner/agency editing flow.
        // Files are temporarily stored under ads/temp/tours/{filename}.
        if ($adId === 'temp') {
            /** @var User|null $user */
            $user = $request->user();
            if (!$user instanceof User) {
                return response('Forbidden', 403, [
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'GET, HEAD',
                ]);
            }

            if (!preg_match('#^[a-zA-Z0-9\-_.\/]+$#', $path)) {
                return response('Bad Request', 400, [
                    'Access-Control-Allow-Origin' => '*',
                ]);
            }

            $tempPath = 'ads/temp/tours/'.$path;
            if (!Storage::disk()->exists($tempPath)) {
                return response('Not Found', 404, [
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'GET, HEAD',
                ]);
            }

            $mime = Storage::disk()->mimeType($tempPath) ?: 'image/webp';
            $size = Storage::disk()->size($tempPath);

            return response()->stream(function () use ($tempPath): void {
                $stream = Storage::disk()->readStream($tempPath);
                if ($stream) {
                    fpassthru($stream);
                    fclose($stream);
                }
            }, 200, [
                'Content-Type' => $mime,
                'Content-Length' => $size,
                'Cache-Control' => 'public, max-age=3600',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD',
            ]);
        }

        if (!Str::isUuid($adId)) {
            return response('Not Found', 404, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD',
            ]);
        }

        $ad = Ad::query()->find($adId);
        if (!$ad || !$ad->has_3d_tour) {
            return response('Not Found', 404, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD',
            ]);
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
            return response('Forbidden', 403, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD',
            ]);
        }

        // Prevent path traversal — only allow safe path characters and forward slashes.
        if (!preg_match('#^[a-zA-Z0-9\-_.\/]+$#', $normalizedPath)) {
            return response('Bad Request', 400, [
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        // Resolve the storage path — new structure first, legacy path as fallback.
        $newPath = 'ads/'.$adId.'/tours/'.$normalizedPath;
        $legacyPath = 'tours/'.$adId.'/'.$normalizedPath;
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
