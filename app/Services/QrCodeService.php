<?php

declare(strict_types=1);

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Throwable;

/**
 * Renders branded QR codes as SVG and PNG for owner assets (placards, business cards, API).
 *
 * URL construction lives in {@see AdUrlBuilder}. This class is responsible
 * only for visual QR rendering.
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
    public function __construct() {}

    private const string BRAND = '#F6475F';

    private const string DARK = '#000000';

    private const string LIGHT = '#FFFFFF';

    // ─── QR rendering ──────────────────────────────────────────────────────

    /**
     * Returns a data URI (image/svg+xml;base64,...) for browser <img src>.
     * SVG is rendered natively by all modern browsers — no Imagick/rsvg needed.
     * Use this for JSON API responses displayed in the owner dialog.
     */
    public function svgDataUriForUrl(string $targetUrl): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->renderRichSvg($targetUrl));
    }

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
     * Build the branded SVG key-silhouette QR code:
     *   • Portrait canvas  (1000 × 1380)
     *   • Key head  = circular QR matrix (upper portion, ECC-H + circular dots)
     *   • Key stem  = plain rectangle below the head (clip-path only)
     *   • Key teeth = decorative notches on the right side of the stem outline
     *   • Brand-gradient dashed rings outside the head circle
     *   • KeyHome logo at the head centre
     *   • KEYHOME.APP label at the bottom
     */
    public function renderRichSvg(string $targetUrl): string
    {
        $rawQrSvg = new QRCode($this->buildOptions())->render($targetUrl);

        // ── Canvas (portrait key silhouette) ──────────────────────────────
        $cw = 1000.0;
        $ch = 1380.0;
        $cx = $cw / 2.0;           // 500

        // QR head sits in the upper portion of the canvas.
        $headCy = 460.0;
        $qrSize = $cw * 0.64;          // 640 — matrix side
        $qrX = $cx - $qrSize / 2.0; // 180
        $qrY = $headCy - $qrSize / 2.0; // 140
        $clipR = $qrSize * 0.5;        // 320 — inscribed circle radius

        // ── QR matrix transform ───────────────────────────────────────────
        $vb = $this->extractViewBox($rawQrSvg) ?? '0 0 100 100';
        $parts = array_map(floatval(...), preg_split('/\s+/', trim($vb)) ?: []);
        $vbX = $parts[0] ?? 0.0;
        $vbY = $parts[1] ?? 0.0;
        $vbW = $parts[2] ?? 100.0;
        $vbH = $parts[3] ?? 100.0;
        $scale = $qrSize / max($vbW, $vbH);
        $tx = $qrX - $vbX * $scale;
        $ty = $qrY - $vbY * $scale;

        $inner = preg_replace('#<\?xml[^>]+\?>\s*#', '', (string) $rawQrSvg) ?? $rawQrSvg;
        $inner = preg_replace('#</?svg[^>]*>#i', '', (string) $inner) ?? '';

        // ── Finder-pattern preservation (QR scanners require these) ───────
        $moduleSize = $qrSize / max($vbW, 1.0);
        $quietzone = 4.0;
        $finderModules = 7.0;
        $finderSize = $finderModules * $moduleSize;
        $finderInset = $quietzone * $moduleSize;
        $pad = $moduleSize;

        $tlX = $qrX + $finderInset - $pad;
        $tlY = $qrY + $finderInset - $pad;
        $trX = $qrX + $qrSize - $finderInset - $finderSize - $pad;
        $trY = $qrY + $finderInset - $pad;
        $blX = $qrX + $finderInset - $pad;
        $blY = $qrY + $qrSize - $finderInset - $finderSize - $pad;
        $fb = $finderSize + 2.0 * $pad;

        // ── Key stem dimensions ───────────────────────────────────────────
        $stemW = 155.0;
        $stemX = $cx - $stemW / 2.0;      // 422.5
        $stemTop = $headCy + $clipR + 8.0;  // just below head circle (≈ 788)
        $stemBot = 1258.0;

        // ── Clip path: head circle + finder rects + stem rectangle ────────
        $clipId = 'kh-key-clip';
        $clipDef = sprintf(
            '<defs><clipPath id="%s">'
                .'<circle cx="%F" cy="%F" r="%F"/>'
                .'<rect x="%F" y="%F" width="%F" height="%F"/>'
                .'<rect x="%F" y="%F" width="%F" height="%F"/>'
                .'<rect x="%F" y="%F" width="%F" height="%F"/>'
                .'<rect x="%F" y="%F" width="%F" height="%F"/>'
                .'</clipPath></defs>',
            $clipId,
            $cx, $headCy, $clipR,
            $tlX, $tlY, $fb, $fb,
            $trX, $trY, $fb, $fb,
            $blX, $blY, $fb, $fb,
            $stemX, $stemTop, $stemW, $stemBot - $stemTop
        );

        $bg = sprintf(
            '<rect x="12" y="12" width="%F" height="%F" rx="64" ry="64" fill="#FFF5F6"/>',
            $cw - 24.0, $ch - 24.0
        );
        $rings = $this->renderDecorativeRings($cx, $headCy, $qrSize, $targetUrl);
        $outline = $this->renderKeyOutline($cx, $headCy, $clipR, $stemX, $stemW, $stemTop, $stemBot);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %F %F" width="%F" height="%F" preserveAspectRatio="xMidYMid meet" role="img" aria-label="QR Code KeyHome">'
                .'%s'
                .'%s'
                .'%s'
                .'<g clip-path="url(#%s)"><rect x="0" y="0" width="%F" height="%F" fill="#FFFFFF"/>'
                .'<g transform="translate(%F %F) scale(%F %F)">%s</g></g>'
                .'%s'
                .'</svg>',
            $cw, $ch, $cw, $ch,
            $clipDef,
            $bg,
            $rings,
            $clipId, $cw, $ch,
            $tx, $ty, $scale, $scale, $inner,
            $outline
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
     * Five concentric dashed rings outside the head circle.
     * Brand-gradient palette: crimson → pink — creates a "glow" halo around
     * the key head without competing with QR scan contrast.
     */
    private function renderDecorativeRings(float $cx, float $cy, float $qrSize, string $seed): string
    {
        $clipR = $qrSize * 0.5;
        $h = crc32($seed);

        /** @var list<array{r: float, w: float, dash: string, color: string, offset: float}> $spec */
        $spec = [
            ['r' => $clipR + 9,  'w' => 3.5, 'dash' => '5 12', 'color' => '#F6475F', 'offset' => (float) ($h % 13)],
            ['r' => $clipR + 23, 'w' => 2.8, 'dash' => '3 10', 'color' => '#FF6B7A', 'offset' => (float) (($h >> 3) % 17)],
            ['r' => $clipR + 37, 'w' => 3.2, 'dash' => '6 15', 'color' => '#FF8F9D', 'offset' => (float) (($h >> 6) % 11)],
            ['r' => $clipR + 51, 'w' => 2.5, 'dash' => '2 11', 'color' => '#FFB3BB', 'offset' => (float) (($h >> 9) % 19)],
            ['r' => $clipR + 65, 'w' => 2.8, 'dash' => '7 17', 'color' => '#FFD4D9', 'offset' => (float) (($h >> 12) % 15)],
        ];

        $out = '';
        foreach ($spec as $ring) {
            $out .= sprintf(
                '<circle cx="%.2f" cy="%.2f" r="%.2f" fill="none" stroke="%s" stroke-width="%.2f" stroke-dasharray="%s" stroke-dashoffset="%.2f" stroke-linecap="round" opacity="0.90"/>',
                $cx, $cy, $ring['r'], $ring['color'], $ring['w'], $ring['dash'], $ring['offset']
            );
        }

        return $out;
    }

    /**
     * Decorative key outline drawn ON TOP of the clipped QR matrix:
     *   • Subtle brand-tinted fill of the stem rectangle
     *   • Crisp stroke outline of the stem with 3 notch teeth on the right
     *   • Thin ring border around the key head circle
     */
    private function renderKeyOutline(
        float $cx,
        float $headCy,
        float $clipR,
        float $stemX,
        float $stemW,
        float $stemTop,
        float $stemBot
    ): string {
        $brand = self::BRAND;
        $stemRight = $stemX + $stemW;
        $rx = 14.0;
        $span = $stemBot - $stemTop;
        $toothD = 62.0;
        $toothH = 68.0;

        $t1Y = $stemTop + $span * 0.20;
        $t2Y = $stemTop + $span * 0.48;
        $t3Y = $stemTop + $span * 0.72;

        // Stem outline path — clockwise from top-left, teeth on right side.
        $d = sprintf(
            'M %.2f %.2f '
            .'L %.2f %.2f '
            .'Q %.2f %.2f %.2f %.2f '
            .'L %.2f %.2f '
            .'Q %.2f %.2f %.2f %.2f '
            .'L %.2f %.2f L %.2f %.2f L %.2f %.2f L %.2f %.2f '
            .'L %.2f %.2f L %.2f %.2f L %.2f %.2f L %.2f %.2f '
            .'L %.2f %.2f L %.2f %.2f L %.2f %.2f L %.2f %.2f '
            .'L %.2f %.2f Z',
            $stemX, $stemTop,
            $stemX, $stemBot - $rx,
            $stemX, $stemBot, $stemX + $rx, $stemBot,
            $stemRight - $rx, $stemBot,
            $stemRight, $stemBot, $stemRight, $stemBot - $rx,
            $stemRight, $t3Y + $toothH,
            $stemRight + $toothD, $t3Y + $toothH,
            $stemRight + $toothD, $t3Y,
            $stemRight, $t3Y,
            $stemRight, $t2Y + $toothH,
            $stemRight + $toothD, $t2Y + $toothH,
            $stemRight + $toothD, $t2Y,
            $stemRight, $t2Y,
            $stemRight, $t1Y + $toothH,
            $stemRight + $toothD, $t1Y + $toothH,
            $stemRight + $toothD, $t1Y,
            $stemRight, $t1Y,
            $stemRight, $stemTop
        );

        return
            sprintf('<path d="%s" fill="%s" fill-opacity="0.06"/>', $d, $brand)
            .sprintf('<path d="%s" fill="none" stroke="%s" stroke-width="4" stroke-linejoin="round" opacity="0.35"/>', $d, $brand)
            .sprintf('<circle cx="%.2f" cy="%.2f" r="%.2f" fill="none" stroke="%s" stroke-width="3.5" opacity="0.22"/>', $cx, $headCy, $clipR + 2.0, $brand);
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
