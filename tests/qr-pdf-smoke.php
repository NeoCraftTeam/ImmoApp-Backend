<?php

declare(strict_types=1);

/**
 * Manual smoke test — generates a sample QR PNG, A5 placarde PDF and
 * 90×55 mm business card PDF into /tmp for visual review.
 *
 *   php tests/qr-pdf-smoke.php
 */
use App\Models\Ad;
use App\Models\User;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$svc = $app->make(QrCodeService::class);

// 1) Standalone branded QR PNG
$png = $svc->renderRichPng('https://keyhome.app/test');
file_put_contents('/tmp/qr-rich.png', $png);
echo 'Rich PNG: '.strlen((string) $png)." bytes -> /tmp/qr-rich.png\n";

// 2) Placarde PDF — pick first ad in DB if any
$ad = Ad::with(['quarter.city', 'ad_type'])->first();
if ($ad !== null) {
    $url = $svc->adListingUrl($ad, 'placard');
    $pdf = Pdf::loadView('pdf.ad-placarde', [
        'ad' => $ad,
        'publicUrl' => $url,
        'qrDataUri' => $svc->pngDataUriForUrl($url),
        'coverImage' => null,
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
    file_put_contents('/tmp/placarde-sample.pdf', $pdf->output());
    echo 'Placarde PDF: '.strlen($pdf->output())." bytes -> /tmp/placarde-sample.pdf\n";
}

// 3) Business card PDF — pick first agent
$user = User::where('role', 'agent')->first();
if ($user !== null) {
    $url = $svc->landlordProfileUrl($user, 'visitcard');
    $pdf = Pdf::loadView('pdf.business-card', [
        'user' => $user,
        'profileUrl' => $url,
        'qrDataUri' => $svc->pngDataUriForUrl($url),
        'avatarDataUri' => null,
        'adsCount' => 0,
        'roleLabel' => 'Bailleur',
        'whatsappNumber' => null,
    ])
        ->setPaper([0, 0, 255.118, 155.906], 'landscape')
        ->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
            'dpi' => 150,
        ]);
    file_put_contents('/tmp/business-card-sample.pdf', $pdf->output());
    echo 'Card PDF: '.strlen($pdf->output())." bytes -> /tmp/business-card-sample.pdf\n";
}
