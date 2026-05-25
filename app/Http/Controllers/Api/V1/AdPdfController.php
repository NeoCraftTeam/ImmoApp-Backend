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
    /**
     * @OA\Get(
     *     path="/api/v1/ads/{ad}/pdf",
     *     summary="Télécharger la fiche PDF d'une annonce",
     *     description="Génère et retourne une fiche A4 imprimable de l'annonce au format PDF. Les annonces publiques sont accessibles sans authentification. Le propriétaire/admin peut télécharger n'importe quel statut.",
     *     operationId="downloadAdPdf",
     *     tags={"📄 PDF / QR Code"},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, description="UUID de l'annonce", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Fichier PDF (application/pdf)",
     *
     *         @OA\MediaType(mediaType="application/pdf", @OA\Schema(type="string", format="binary"))
     *     ),
     *
     *     @OA\Response(response=404, description="Annonce introuvable ou accès refusé")
     * )
     */
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
