<?php

declare(strict_types=1);

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;

/**
 * Assembles the branded KeyHome payment PDFs (history export + single receipt).
 *
 * Centralises the shared DomPDF wiring — the embedded base64 logo, A4 portrait
 * paper, and the DejaVu / no-remote render options — so both endpoints render
 * identically. Extracted from PaymentController; callers pass the Blade view
 * and its data and receive the configured PDF ready to download or stream.
 */
final class PaymentPdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(string $view, array $data): DomPDF
    {
        return Pdf::loadView($view, array_merge($data, ['logoBase64' => self::logoBase64()]))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ]);
    }

    /**
     * Inline the KeyHome logo as a base64 data URI, or null when the asset is missing.
     */
    private static function logoBase64(): ?string
    {
        $logoPath = public_path('images/keyhomelogo_transparent.png');

        return file_exists($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;
    }
}
