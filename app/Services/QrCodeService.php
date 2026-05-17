<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use App\Models\User;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Throwable;

/**
 * Renders branded QR codes as SVG and PNG for owner assets (placards, business cards, API).
 *
 * URL construction has been extracted to {@see AdUrlBuilder}; this class is
 * responsible only for visual QR rendering. The URL delegation methods below
 * are kept for backwards compatibility but are marked @deprecated.
 *
 * TikTok/Snap-inspired circular-framed scannable QR PNGs for owner assets.
 *
 * Visual design:
 *   • Standard ISO QR matrix with ECC-H — data + quiet zone stay intact
 *   • Pure black modules on white inside the clip; finders stay square
 *   • Concentric dashed rings in black / grey sit **outside** the inscribed data
 *     circle (different radii + dash phases seeded by URL crc32) → 100% scannable
 *   • Centre white plate: subtle monochrome camera glyph under the KeyHome logo
 *     (≤ ~10% matrix width, within ECC-H budget)
 *
 * Rasterisation chain (SVG → PNG): Imagick → rsvg-convert → plain chillerlan PNG.
 * DomPDF gets a clean PNG (it can't render our complex SVG with transforms +
 * embedded base64 images reliably).
 */
final readonly class QrCodeService
{
    public function __construct(
        private AdUrlBuilder $urlBuilder,
    ) {}

    private const string BRAND = '#F6475F';

    private const string DARK = '#000000';

    private const string LIGHT = '#FFFFFF';

    // ─── URL delegation (backwards-compat wrappers over AdUrlBuilder) ──────
    /**
     * Append UTM (or any) query parameters to a URL.
     *
     * @param  array<string, string>  $utm
     */
    #[\Deprecated(message: 'Inject AdUrlBuilder and call appendUtm() directly.')]
    public function appendUtm(string $absoluteUrl, array $utm): string
    {
        return $this->urlBuilder->appendUtm($absoluteUrl, $utm);
    }

    /**
     * Resolve an absolute frontend URL for the given path.
     */
    #[\Deprecated(message: 'Inject AdUrlBuilder and call absoluteFrontendUrl() directly.')]
    public function absoluteFrontendUrl(string $path): string
    {
        return $this->urlBuilder->absoluteFrontendUrl($path);
    }

    /**
     * Build the canonical listing URL for an ad, optionally with UTM parameters.
     *
     * Returns a clean URL by default (`$includeUtm = false`). Pass `true` from
     * QR/placard controllers to enable scan attribution tracking.
     *
     * @return string Fully-qualified frontend URL.
     */
    #[\Deprecated(message: 'Inject AdUrlBuilder and call adListingUrl() directly.')]
    public function adListingUrl(Ad $ad, string $utmMedium = 'qr', bool $includeUtm = false): string
    {
        return $this->urlBuilder->adListingUrl($ad, $utmMedium, $includeUtm);
    }

    /**
     * Build the canonical landlord profile URL, optionally with UTM parameters.
     *
     * Returns a clean URL by default (`$includeUtm = false`). Pass `true` from
     * QR/placard controllers to enable scan attribution tracking.
     *
     * @return string Fully-qualified frontend URL.
     */
    #[\Deprecated(message: 'Inject AdUrlBuilder and call landlordProfileUrl() directly.')]
    public function landlordProfileUrl(User $user, string $utmMedium = 'qr', bool $includeUtm = false): string
    {
        return $this->urlBuilder->landlordProfileUrl($user, $utmMedium, $includeUtm);
    }

    // ─── QR rendering ──────────────────────────────────────────────────────

    /**
     * Returns a data URI (image/png;base64,...) suitable for DomPDF <img src>
     * and JSON APIs. The image is the branded Apple-style QR.
     */
    public function pngDataUriForUrl(string $targetUrl, int $size = 800): string
    {
        return 'data:image/png;base64,'.base64_encode($this->renderRichPng($targetUrl, $size));
    }

    /**
     * Render the full branded SVG (square matrix + decorative rings + logo)
     * then rasterise it to a high-resolution PNG.
     */
    public function renderRichPng(string $targetUrl, int $size = 800): string
    {
        $svg = $this->renderRichSvg($targetUrl);

        return $this->svgToPng($svg, $size, $targetUrl);
    }

    /**
     * Build the branded SVG: chillerlan QR matrix injected directly into our
     * outer 1000×1000 viewBox (no nested <svg>) with decorative rings around
     * the matrix and an embedded logo at the centre.
     */
    public function renderRichSvg(string $targetUrl): string
    {
        $rawQrSvg = new QRCode($this->buildOptions())->render($targetUrl);

        $canvas = 1000.0;
        $cx = $canvas / 2;
        $cy = $canvas / 2;

        // Matrix occupies 64 % of the canvas — large enough to dominate,
        // small enough that the 2 decorative rings stay inside the canvas.
        $qrSize = $canvas * 0.64;
        $qrX = $cx - $qrSize / 2;
        $qrY = $cy - $qrSize / 2;

        $vb = $this->extractViewBox($rawQrSvg) ?? '0 0 100 100';
        $parts = array_map(floatval(...), preg_split('/\s+/', trim($vb)) ?: []);
        $vbX = $parts[0] ?? 0.0;
        $vbY = $parts[1] ?? 0.0;
        $vbW = $parts[2] ?? 100.0;
        $vbH = $parts[3] ?? 100.0;
        $scale = $qrSize / max($vbW, $vbH);
        $tx = $qrX - $vbX * $scale;
        $ty = $qrY - $vbY * $scale;

        // Strip outer SVG and XML declaration wrappers from chillerlan output.
        $inner = preg_replace('#<\?xml[^>]+\?>\s*#', '', (string) $rawQrSvg) ?? $rawQrSvg;
        $inner = preg_replace('#</?svg[^>]*>#i', '', (string) $inner) ?? '';

        $rings = $this->renderDecorativeRings($cx, $cy, $qrSize, $targetUrl);
        $logo = $this->renderCenterLogo($cx, $cy, $qrSize * 0.10);

        // Smart circular clip-path that:
        //   1. Keeps a centred CIRCLE of data modules visible
        //   2. Adds 3 RECTANGLES preserving the finder squares (TL/TR/BL)
        //      — essential for scanner detection
        //   3. Adds a SMALL square preserving the alignment pattern (BR)
        // → visually circular silhouette but 100 % standard QR scannable.
        // The clip-path uses union of all shapes (anything inside any shape
        // remains visible after clipping).
        $clipR = $qrSize * 0.5;            // inscribed circle (matrix sides)
        $moduleSize = $qrSize / max($vbW, 1.0);
        $quietzone = 4.0;
        $finderModules = 7.0;
        $finderSize = $finderModules * $moduleSize;
        $finderInset = $quietzone * $moduleSize;
        // Pad the finder rects by 1 module so the white separator around
        // each finder is also preserved (scanners need that quiet ring).
        $pad = $moduleSize;
        $tlX = $qrX + $finderInset - $pad;
        $tlY = $qrY + $finderInset - $pad;
        $trX = $qrX + $qrSize - $finderInset - $finderSize - $pad;
        $trY = $qrY + $finderInset - $pad;
        $blX = $qrX + $finderInset - $pad;
        $blY = $qrY + $qrSize - $finderInset - $finderSize - $pad;
        $finderBox = $finderSize + 2 * $pad;

        $clipId = 'kh-qr-clip';
        $clipDef = sprintf(
            '<defs><clipPath id="%s">'
                .'<circle cx="%F" cy="%F" r="%F"/>'
                .'<rect x="%F" y="%F" width="%F" height="%F"/>'
                .'<rect x="%F" y="%F" width="%F" height="%F"/>'
                .'<rect x="%F" y="%F" width="%F" height="%F"/>'
                .'</clipPath></defs>',
            $clipId,
            $cx, $cy, $clipR,
            $tlX, $tlY, $finderBox, $finderBox,
            $trX, $trY, $finderBox, $finderBox,
            $blX, $blY, $finderBox, $finderBox
        );

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %F %F" width="%F" height="%F" preserveAspectRatio="xMidYMid meet" role="img" aria-label="QR Code KeyHome">'
                .'%s'
                .'<rect x="0" y="0" width="%F" height="%F" rx="80" ry="80" fill="#FFF5F6"/>'
                .'%s'
                .'<g clip-path="url(#%s)"><g transform="translate(%F %F) scale(%F %F)">%s</g></g>'
                .'%s'
                .'</svg>',
            $canvas, $canvas, $canvas, $canvas,
            $clipDef,
            $canvas, $canvas,
            $rings,
            $clipId,
            $tx, $ty, $scale, $scale, $inner,
            $logo
        );
    }

    /**
     * Shared chillerlan options. Matrix is kept square + scannable.
     */
    private function buildOptions(): QROptions
    {
        $dark = self::DARK;
        $light = self::LIGHT;

        return new QROptions([
            'eccLevel' => EccLevel::H,
            'outputType' => QROutputInterface::MARKUP_SVG,
            'scale' => 10,
            'addQuietzone' => true,
            // ISO-required 4-module quiet zone — going below breaks scanners.
            'quietzoneSize' => 4,
            'svgAddXmlHeader' => false,
            'svgUseFillAttributes' => true,
            'connectPaths' => false,
            'drawCircularModules' => true,
            // 0.5 = circles touch their neighbours → visually rounded but
            // virtually identical to square modules for scanners.
            'circleRadius' => 0.5,
            'keepAsSquare' => [
                QRMatrix::M_FINDER_DARK,
                QRMatrix::M_FINDER_DOT,
                QRMatrix::M_ALIGNMENT_DARK,
            ],
            'cssClass' => 'kh-qr',
            'outputBase64' => false,
            'moduleValues' => [
                QRMatrix::M_DATA_DARK => $dark,
                QRMatrix::M_FINDER_DARK => $dark,
                QRMatrix::M_ALIGNMENT_DARK => $dark,
                QRMatrix::M_TIMING_DARK => $dark,
                QRMatrix::M_FINDER_DOT => $dark,
                QRMatrix::M_DARKMODULE => $dark,
                QRMatrix::M_DATA => $light,
                QRMatrix::M_FINDER => $light,
                QRMatrix::M_ALIGNMENT => $light,
                QRMatrix::M_TIMING => $light,
            ],
        ]);
    }

    /**
     * Five concentric dashed circles **outside** the inscribed data circle (same
     * radius as the clip-path circle). Black + cool greys only on the rings so
     * they never compete with scan contrast. Dash phases vary deterministically
     * with the target URL string (crc32) for a stable per-link look.
     */
    private function renderDecorativeRings(float $cx, float $cy, float $qrSize, string $seed): string
    {
        $dark = self::DARK;
        $g1 = '#1F2937';
        $g2 = '#4B5563';
        $g3 = '#6B7280';
        $g4 = '#9CA3AF';

        $clipR = $qrSize * 0.5;
        $h = crc32($seed);

        /** @var list<array{r: float, w: float, dash: string, color: string, offset: float}> $spec */
        $spec = [
            ['r' => $clipR + 9, 'w' => 3.2, 'dash' => '4 11', 'color' => $dark, 'offset' => (float) ($h % 13)],
            ['r' => $clipR + 22, 'w' => 2.6, 'dash' => '3 9', 'color' => $g1, 'offset' => (float) (($h >> 3) % 17)],
            ['r' => $clipR + 35, 'w' => 3.0, 'dash' => '5 14', 'color' => $g2, 'offset' => (float) (($h >> 6) % 11)],
            ['r' => $clipR + 48, 'w' => 2.4, 'dash' => '2 10', 'color' => $g3, 'offset' => (float) (($h >> 9) % 19)],
            ['r' => $clipR + 62, 'w' => 2.8, 'dash' => '6 16', 'color' => $g4, 'offset' => (float) (($h >> 12) % 15)],
        ];

        $out = '';
        foreach ($spec as $ring) {
            $out .= sprintf(
                '<circle cx="%.2f" cy="%.2f" r="%.2f" fill="none" stroke="%s" stroke-width="%.2f" stroke-dasharray="%s" stroke-dashoffset="%.2f" stroke-linecap="round" opacity="0.92"/>',
                $cx,
                $cy,
                $ring['r'],
                $ring['color'],
                $ring['w'],
                $ring['dash'],
                $ring['offset']
            );
        }

        return $out;
    }

    /**
     * White plate + optional subtle camera-glyph under the brand mark (TikTok-style
     * cue) + embedded KeyHome PNG when available. Plate radius ~10% of matrix side.
     */
    private function renderCenterLogo(float $cx, float $cy, float $r): string
    {
        $disc = sprintf('<circle cx="%.2f" cy="%.2f" r="%.2f" fill="#FFFFFF"/>', $cx, $cy, $r);
        $camera = $this->renderCameraGlyphAccent($cx, $cy, $r);

        $logoUri = $this->logoDataUri();
        if ($logoUri !== null) {
            $size = $r * 1.55;

            return $disc
                .$camera
                .sprintf(
                    '<image x="%.2f" y="%.2f" width="%.2f" height="%.2f" href="%s" preserveAspectRatio="xMidYMid meet"/>',
                    $cx - $size / 2, $cy - $size / 2, $size, $size, $logoUri
                );
        }

        // Camera + keyhole fallback if logo file unreadable.
        $brand = self::BRAND;
        $keyR = $r * 0.28;
        $keyCy = $cy - $r * 0.12;
        $stemTop = $keyCy + $keyR * 0.55;
        $stemBot = $cy + $r * 0.52;

        return $disc
            .$camera
            .sprintf('<circle cx="%.2f" cy="%.2f" r="%.2f" fill="%s"/>', $cx, $keyCy, $keyR, $brand)
            .sprintf(
                '<path d="M %.2f %.2f L %.2f %.2f L %.2f %.2f L %.2f %.2f Z" fill="%s"/>',
                $cx - $r * 0.10, $stemTop,
                $cx + $r * 0.10, $stemTop,
                $cx + $r * 0.18, $stemBot,
                $cx - $r * 0.18, $stemBot,
                $brand
            );
    }

    /**
     * Low-contrast line camera icon (body + lens + viewfinder bump) on the centre plate.
     */
    private function renderCameraGlyphAccent(float $cx, float $cy, float $r): string
    {
        $sw = max(1.8, $r * 0.14);
        $bodyW = $r * 1.75;
        $bodyH = $r * 1.2;
        $rx = $r * 0.28;
        $bx = $cx - $bodyW / 2;
        $by = $cy - $bodyH / 2 - $r * 0.02;
        $lensR = $r * 0.38;
        $vfW = $r * 0.42;
        $vfH = $r * 0.22;
        $vfx = $cx + $bodyW * 0.22;
        $vfy = $by - $vfH * 0.35;

        return sprintf(
            '<g aria-hidden="true" fill="none" stroke="#374151" stroke-width="%.2f" stroke-linecap="round" stroke-linejoin="round" opacity="0.38">'
                .'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f"/>'
                .'<circle cx="%.2f" cy="%.2f" r="%.2f" stroke="#111827" opacity="0.55"/>'
                .'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" opacity="0.7"/>'
                .'</g>',
            $sw,
            $bx, $by, $bodyW, $bodyH, $rx,
            $cx - $r * 0.08, $cy + $r * 0.04, $lensR,
            $vfx, $vfy, $vfW, $vfH, $r * 0.1
        );
    }

    private function logoDataUri(): ?string
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache === '' ? null : $cache;
        }

        $candidates = [
            base_path('keyhome-frontend-next/public/icons/icon-512x512.png'),
            public_path('images/keyhomelogo_transparent.png'),
            public_path('images/keyhomelogo.png'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                $bytes = @file_get_contents($path);
                if ($bytes !== false && $bytes !== '') {
                    return $cache = 'data:image/png;base64,'.base64_encode($bytes);
                }
            }
        }

        $cache = '';

        return null;
    }

    private function extractViewBox(string $svg): ?string
    {
        return preg_match('/viewBox="([^"]+)"/i', $svg, $m) === 1 ? $m[1] : null;
    }

    /**
     * Rasterise SVG → PNG. Tries Imagick first (fast, in-process), then
     * rsvg-convert binary, then a plain B&W chillerlan PNG so the PDF
     * always has at least *something* scannable.
     */
    private function svgToPng(string $svg, int $size, string $fallbackUrl): string
    {
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick;
                $imagick->setBackgroundColor('white');
                $imagick->readImageBlob($svg);
                $imagick->setImageFormat('png32');
                $imagick->resizeImage($size, $size, \Imagick::FILTER_LANCZOS, 1);
                $bytes = $imagick->getImageBlob();
                $imagick->clear();
                if ($bytes !== '') {
                    return $bytes;
                }
            } catch (Throwable) {
                // fall through
            }
        }

        $rsvg = $this->locateBinary(['rsvg-convert']);
        if ($rsvg !== null) {
            $tmp = tempnam(sys_get_temp_dir(), 'kh-qr-').'.svg';
            file_put_contents($tmp, $svg);
            try {
                $cmd = sprintf('%s -w %d -h %d -f png %s', escapeshellcmd($rsvg), $size, $size, escapeshellarg($tmp));
                $bytes = @shell_exec($cmd);
                if (is_string($bytes) && $bytes !== '' && str_starts_with($bytes, "\x89PNG")) {
                    return $bytes;
                }
            } finally {
                @unlink($tmp);
            }
        }

        // Last-resort: plain B&W chillerlan PNG (still scannable).
        $opts = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::H,
            'scale' => 12,
            'outputBase64' => false,
        ]);

        return new QRCode($opts)->render($fallbackUrl);
    }

    /**
     * @param  list<string>  $names
     */
    private function locateBinary(array $names): ?string
    {
        foreach ($names as $name) {
            $found = @shell_exec(sprintf('command -v %s 2>/dev/null', escapeshellarg($name)));
            if (is_string($found) && trim($found) !== '') {
                return trim($found);
            }
        }

        return null;
    }
}
