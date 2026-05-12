<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Generates a printable PDF sheet for a single ad listing.
 *
 * Access: public (available ads) or authenticated (any status for owner/admin)
 */
final class AdPdfController
{
    public function download(Request $request, Ad $ad): Response
    {
        $user = $request->user();

        // Only available ads are publicly downloadable; owner/admin can download any
        if ($ad->status->value !== 'available') {
            $isOwnerOrAdmin = $user && ($user->id === $ad->user_id || $user->isAdmin());
            abort_unless($isOwnerOrAdmin, 404);
        }

        $ad->loadMissing(['quarter.city', 'ad_type', 'media', 'user.agency']);

        $logoPath = public_path('images/keyhomelogo_transparent.png');
        $logoBase64 = file_exists($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $primaryImage = $ad->getFirstMediaUrl('images', 'large') ?: $ad->getFirstMediaUrl('images');

        $pdf = Pdf::loadView('ads.pdf', [
            'ad' => $ad,
            'logoBase64' => $logoBase64,
            'primaryImage' => $primaryImage ?: null,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $slug = $ad->slug ?? $ad->id;
        $filename = 'annonce-'.$slug.'.pdf';

        return $pdf->download($filename);
    }
}
