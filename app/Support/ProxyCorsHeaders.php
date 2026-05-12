<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class ProxyCorsHeaders
{
    /**
     * CORS headers for tour-image and media-proxy responses.
     * Restricts Access-Control-Allow-Origin to configured frontend domains.
     *
     * @return array<string, string>
     */
    public static function for(Request $request): array
    {
        $allowed = config('proxy-cors.allowed_origins', []);

        if (in_array('*', $allowed, true)) {
            return [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD',
            ];
        }

        $origin = $request->header('Origin');
        if ($origin && in_array($origin, $allowed, true)) {
            return [
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => 'GET, HEAD',
            ];
        }

        return ['Access-Control-Allow-Methods' => 'GET, HEAD'];
    }
}
