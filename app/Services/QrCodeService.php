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
 * Visual design (owner panel — teal):
 *   • Portrait key silhouette — decorative stem + dashed rings only
 *   • Square ISO QR matrix — full quiet zone, never circle-clipped
 *   • White scan plate behind the matrix for reliable phone decoding
 *   • Teal palette (#0D9488) on pale mint background (#F0FDFA)
 *
 * Rasterisation chain (SVG → PNG): Imagick → rsvg-convert → plain chillerlan PNG.
 * DomPDF gets a clean PNG (it can't render our complex SVG with transforms +
 * embedded base64 images reliably).
 */
final readonly class QrCodeService
{
    public function __construct() {}

    /** Owner-panel teal — matches brandAgent.primary in the PWA. */
    private const string BRAND = '#0D9488';

    private const string BRAND_LIGHT = '#14B8A6';

    private const string BRAND_PALE = '#F0FDFA';

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
     * Plain square QR (black on white) for A5 placards — no key silhouette.
     */
    public function plainPngDataUriForUrl(string $targetUrl): string
    {
        $opts = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::H,
            'scale' => 14,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
            'outputBase64' => false,
        ]);

        return 'data:image/png;base64,'.base64_encode((string) new QRCode($opts)->render($targetUrl));
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
        $rawQrSvg = new QRCode($this->buildRichOptions())->render($targetUrl);

        // ── Canvas (portrait key silhouette) ──────────────────────────────
        $cw = 1100.0;
        $ch = 1520.0;
        $cx = $cw / 2.0;

        $headCy = 490.0;
        $qrSize = $cw * 0.62;
        $qrX = $cx - $qrSize / 2.0;
        $qrY = $headCy - $qrSize / 2.0;
        // Decorative circle / rings only — must NOT clip the QR matrix.
        $headR = $qrSize / 2.0;
        $ringBaseR = $qrSize * M_SQRT2 / 2.0;

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

        // ── Key stem (pure decoration — no QR clipped into it) ────────────
        $stemW = 160.0;
        $stemX = $cx - $stemW / 2.0;
        $stemTop = $headCy + $headR + 14.0;
        $stemBot = 1410.0;

        $platePad = 10.0;
        $scanPlate = '<rect x="'.$this->svgFloat($qrX - $platePad).'" y="'.$this->svgFloat($qrY - $platePad)
            .'" width="'.$this->svgFloat($qrSize + 2.0 * $platePad).'" height="'.$this->svgFloat($qrSize + 2.0 * $platePad)
            .'" rx="28" ry="28" fill="'.self::LIGHT.'"/>';

        // Never sprintf() the chillerlan matrix — it may contain '%' sequences.
        $qrBlock = '<g>'.$scanPlate
            .'<g transform="translate('.$this->svgFloat($tx).' '.$this->svgFloat($ty).') scale('
            .$this->svgFloat($scale).' '.$this->svgFloat($scale).')">'
            .$inner
            .'</g></g>';

        $bg = '<rect x="8" y="8" width="'.$this->svgFloat($cw - 16.0).'" height="'.$this->svgFloat($ch - 16.0)
            .'" rx="72" ry="72" fill="'.self::BRAND_PALE.'"/>';
        $rings = $this->renderDecorativeRings($cx, $headCy, $ringBaseR, $targetUrl);
        $outline = $this->renderKeyOutline($cx, $headCy, $ringBaseR, $stemX, $stemW, $stemTop, $stemBot);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$this->svgFloat($cw).' '.$this->svgFloat($ch)
            .'" width="'.$this->svgFloat($cw).'" height="'.$this->svgFloat($ch)
            .'" preserveAspectRatio="xMidYMid meet" role="img" aria-label="QR Code KeyHome">'
            .$bg
            .$rings
            .$qrBlock
            .$outline
            .'</svg>';
    }

    private function svgFloat(float $value): string
    {
        $formatted = sprintf('%.3F', $value);

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Rich branded QR — white quiet zone on a scan plate; square finders intact.
     */
    private function buildRichOptions(): QROptions
    {
        $dark = self::DARK;
        $light = self::LIGHT;

        return new QROptions([
            'eccLevel' => EccLevel::H,
            'outputType' => QROutputInterface::MARKUP_SVG,
            'scale' => 10,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
            'svgAddXmlHeader' => false,
            'svgUseFillAttributes' => true,
            'connectPaths' => true,
            'drawCircularModules' => false,
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
     * Five concentric dashed rings outside the head circle (circumscribed radius).
     * Teal gradient halo — decorative only; QR matrix stays square + unscathed.
     */
    private function renderDecorativeRings(float $cx, float $cy, float $headR, string $seed): string
    {
        $h = crc32($seed);

        /** @var list<array{r: float, w: float, dash: string, color: string, offset: float}> $spec */
        $spec = [
            ['r' => $headR + 8,  'w' => 3.5, 'dash' => '5 12', 'color' => self::BRAND, 'offset' => (float) ($h % 13)],
            ['r' => $headR + 20, 'w' => 2.8, 'dash' => '3 10', 'color' => self::BRAND_LIGHT, 'offset' => (float) (($h >> 3) % 17)],
            ['r' => $headR + 32, 'w' => 3.2, 'dash' => '6 15', 'color' => '#2DD4BF', 'offset' => (float) (($h >> 6) % 11)],
            ['r' => $headR + 44, 'w' => 2.5, 'dash' => '2 11', 'color' => '#5EEAD4', 'offset' => (float) (($h >> 9) % 19)],
            ['r' => $headR + 56, 'w' => 2.8, 'dash' => '7 17', 'color' => '#99F6E4', 'offset' => (float) (($h >> 12) % 15)],
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
     * Decorative key outline (does not alter the QR matrix):
     *   • Subtle brand-tinted fill of the stem rectangle
     *   • Crisp stroke outline of the stem with 3 notch teeth on the right
     *   • Thin ring border around the circumscribed head circle
     */
    private function renderKeyOutline(
        float $cx,
        float $headCy,
        float $headR,
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
            .sprintf('<circle cx="%.2f" cy="%.2f" r="%.2f" fill="none" stroke="%s" stroke-width="3.5" opacity="0.28"/>', $cx, $headCy, $headR + 2.0, $brand);
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
