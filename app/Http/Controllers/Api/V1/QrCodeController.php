<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use App\Models\User;
use App\Services\AdUrlBuilder;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Authenticated owner endpoints: QR PNG, printable A5 placarde, profile
 * business card PDFs and matching HTML preview.
 */
final readonly class QrCodeController
{
    use AuthorizesRequests;

    public function __construct(
        private QrCodeService $qrCodeService,
        private AdUrlBuilder $urlBuilder,
    ) {}

    // ─── Ad endpoints ──────────────────────────────────────────────────────

    public function adMeta(Request $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);

        $adUrl = $this->urlBuilder->adListingUrl($ad, 'qr', true);
        $user = $request->user();
        $profileUrl = $user ? $this->urlBuilder->landlordProfileUrl($user, 'qr', true) : null;

        return response()->json([
            'data' => [
                'ad_url' => $adUrl,
                'profile_url' => $profileUrl,
                'qr_data_uri' => $this->qrCodeService->svgDataUriForUrl($adUrl),
            ],
        ]);
    }

    public function adQrImage(Request $request, Ad $ad): SymfonyResponse
    {
        $this->authorize('update', $ad);

        $adUrl = $this->urlBuilder->adListingUrl($ad, 'qr', true);
        $binary = $this->qrCodeService->renderRichPng($adUrl);

        return response($binary, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="qrcode-'.($ad->slug ?: $ad->id).'.png"',
        ]);
    }

    public function adPlacarde(Request $request, Ad $ad): Response
    {
        $this->authorize('update', $ad);

        $ad->loadMissing(['quarter.city', 'ad_type', 'user', 'media']);

        $adUrl = $this->urlBuilder->adListingUrl($ad, 'qr', true);

        $pdf = Pdf::loadView('pdf.ad-placarde', [
            'ad' => $ad,
            'publicUrl' => $adUrl,
            'qrDataUri' => $this->qrCodeService->pngDataUriForUrl($adUrl),
            'coverImage' => $this->loadAdCoverAsBase64($ad),
            'quarter' => $ad->quarter?->name,
            'city' => $ad->quarter?->city?->name,
        ])
            ->setPaper('a5', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 150,
            ]);

        return $pdf->download('placarde-'.($ad->slug ?: $ad->id).'.pdf');
    }

    // ─── Profile endpoints ─────────────────────────────────────────────────

    public function profileMeta(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $profileUrl = $this->urlBuilder->landlordProfileUrl($user, 'qr', true);

        return response()->json([
            'data' => [
                'profile_url' => $profileUrl,
                'qr_data_uri' => $this->qrCodeService->svgDataUriForUrl($profileUrl),
            ],
        ]);
    }

    public function profileQrImage(Request $request): SymfonyResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $profileUrl = $this->urlBuilder->landlordProfileUrl($user, 'qr', true);
        $binary = $this->qrCodeService->renderRichPng($profileUrl);

        return response($binary, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="qrcode-profil.png"',
        ]);
    }

    /**
     * 90 × 55 mm landscape printable PDF.
     */
    public function businessCard(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $payload = $this->buildBusinessCardPayload($user);

        // 90 mm × 55 mm in PostScript points (1 mm = 2.83465 pt)
        $pdf = Pdf::loadView('pdf.business-card', $payload)
            ->setPaper([0, 0, 255.118, 155.906], 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 150,
            ]);

        return $pdf->download('carte-visite-'.($user->username ?: $user->id).'.pdf');
    }

    /**
     * Self-contained HTML preview (same payload as the PDF) — used as the
     * `srcDoc` of an iframe inside the QR dialog so the in-app preview is a
     * pixel-faithful 1:1 render of the downloadable PDF.
     */
    public function businessCardPreview(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $payload = $this->buildBusinessCardPayload($user);

        $html = view('pdf.business-card-preview', $payload)->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    // ─── Internals ─────────────────────────────────────────────────────────

    /**
     * Shared view data so the PDF and the HTML preview stay perfectly in sync.
     *
     * @return array<string, mixed>
     */
    private function buildBusinessCardPayload(User $user): array
    {
        $user->loadCount(['ads' => fn ($q) => $q->where('status', 'available')]);

        $profileUrl = $this->urlBuilder->landlordProfileUrl($user, 'qr', true);

        $roleLabel = match ($user->role->value ?? 'agent') {
            'admin' => 'Administrateur',
            'agent' => 'Bailleur',
            default => 'Membre KeyHome',
        };

        return [
            'user' => $user,
            'profileUrl' => $profileUrl,
            'qrDataUri' => $this->qrCodeService->pngDataUriForUrl($profileUrl),
            'avatarDataUri' => $this->loadUserAvatarAsBase64($user),
            'adsCount' => (int) ($user->ads_count ?? 0),
            'roleLabel' => $roleLabel,
            'whatsappNumber' => $user->phone_is_whatsapp
                ? preg_replace('/[^0-9]/', '', (string) $user->phone_number)
                : null,
        ];
    }

    private function loadAdCoverAsBase64(Ad $ad): ?string
    {
        $url = $ad->getFirstMediaUrl('images', 'thumb') ?: $ad->getFirstMediaUrl('images');
        if ($url === '') {
            return null;
        }

        try {
            $bytes = @file_get_contents($url);
            if ($bytes === false || $bytes === '') {
                return null;
            }

            return 'data:'.$this->detectMime($bytes).';base64,'.base64_encode($bytes);
        } catch (Throwable) {
            return null;
        }
    }

    private function loadUserAvatarAsBase64(User $user): ?string
    {
        $url = $user->getFirstMediaUrl('avatars');

        if ($url === '') {
            $raw = (string) ($user->avatar ?? '');
            if ($raw === '') {
                return null;
            }
            if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
                $url = $raw;
            } else {
                try {
                    $disk = Storage::disk((string) config('filesystems.app_media_disk'));
                    if ($disk->exists($raw)) {
                        $bytes = $disk->get($raw);
                        if (is_string($bytes) && $bytes !== '') {
                            return 'data:'.$this->detectMime($bytes).';base64,'.base64_encode($bytes);
                        }
                    }
                } catch (Throwable) {
                    // fall through
                }

                return null;
            }
        }

        try {
            $bytes = @file_get_contents($url);
            if ($bytes === false || $bytes === '') {
                return null;
            }

            return 'data:'.$this->detectMime($bytes).';base64,'.base64_encode($bytes);
        } catch (Throwable) {
            return null;
        }
    }

    private function detectMime(string $bytes): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($bytes);

        return is_string($detected) && $detected !== '' ? $detected : 'image/jpeg';
    }
}
