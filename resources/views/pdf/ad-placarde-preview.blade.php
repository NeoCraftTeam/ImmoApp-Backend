<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Aperçu — Pancarte {{ $ad->title }}</title>
    {{--
        Browser-friendly preview of the A5 placard.

        Re-uses the EXACT same DOM/styles as the printable @see pdf.ad-placarde
        view so the on-screen preview is pixel-faithful with the downloaded PDF.
        This HTML render is what fixes the iOS bug: iOS Safari/WebKit refuses to
        display a blob: PDF in an iframe, but renders HTML everywhere. The only
        differences vs the PDF blade are:
          • a transparent surrounding viewport so the host page can centre it,
          • a CSS scale that adapts to the iframe's available size (both axes),
          • a fallback web font (DomPDF embeds DejaVu Sans).
    --}}
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            background: transparent;
            font-family: -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #111827;
            width: 100%; height: 100%; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }

        .placarde {
            width: 148mm; height: 210mm; position: relative; background: #FFFFFF;
            border: 0.5pt solid #E5E7EB; border-radius: 2mm;
            box-shadow: 0 24px 60px -24px rgba(17, 24, 39, 0.22);
            transform-origin: center center; transform: scale(1);
        }

        .header { position: absolute; top: 13mm; left: 12mm; right: 12mm; height: 8mm; }
        .brand { font-size: 12pt; font-weight: 800; letter-spacing: 3px; color: #111827; }
        .brand-rule { width: 16mm; height: 1.1mm; background: #F6475F; margin-top: 1.4mm; }
        .transaction {
            position: absolute; top: 1mm; right: 0;
            font-size: 8.5pt; font-weight: 800; letter-spacing: 2.5px;
            color: #F6475F; text-transform: uppercase;
        }

        .photo {
            position: absolute; top: 25mm; left: 12mm; right: 12mm; height: 84mm;
            background-color: #F3F4F6; background-position: center; background-size: cover;
            background-repeat: no-repeat;
            border: 0.5pt solid #E5E7EB; border-radius: 1.5mm; overflow: hidden;
        }
        .photo-placeholder { text-align: center; color: #9CA3AF; font-size: 9.5pt; padding-top: 38mm; }

        .info { position: absolute; top: 113mm; left: 12mm; right: 12mm; }
        .price { font-size: 23pt; font-weight: 800; color: #111827; line-height: 1; display: inline; }
        .price-suffix { font-size: 9pt; color: #6B7280; font-weight: 600; display: inline; margin-left: 1.5mm; }
        .price-row { margin-bottom: 3mm; }
        .title { font-size: 13pt; font-weight: 700; line-height: 1.25; color: #111827; margin-bottom: 2mm; }
        .address {
            font-size: 9.5pt; color: #6B7280; line-height: 1.4;
            padding-bottom: 3.5mm; margin-bottom: 3.5mm; border-bottom: 0.5pt solid #E5E7EB;
        }
        .features { width: 100%; border-collapse: collapse; }
        .features td { text-align: center; padding: 0; border-right: 0.5pt solid #E5E7EB; width: 33.33%; vertical-align: middle; }
        .features td:last-child { border-right: none; }
        .feature-value { font-size: 13pt; font-weight: 800; color: #111827; line-height: 1.05; }
        .feature-label { font-size: 6.5pt; color: #9CA3AF; text-transform: uppercase; letter-spacing: 1px; margin-top: 0.8mm; }

        .qr-block { position: absolute; bottom: 11mm; left: 12mm; right: 12mm; height: 34mm; border-top: 0.5pt solid #E5E7EB; padding-top: 4mm; }
        .qr-row { width: 100%; border-collapse: collapse; }
        .qr-row td { vertical-align: middle; padding: 0; }
        .qr-cell { width: 32mm; }
        .qr-img { width: 30mm; height: 30mm; display: block; }
        .qr-text { text-align: left; padding-left: 5mm; }
        .qr-cta { font-size: 12.5pt; font-weight: 700; color: #111827; line-height: 1.2; margin-bottom: 1.5mm; }
        .qr-cta .accent { color: #F6475F; }
        .qr-instruction { font-size: 8pt; color: #6B7280; line-height: 1.5; }

        .footer { position: absolute; bottom: 5mm; left: 12mm; right: 12mm; font-size: 6.5pt; color: #9CA3AF; letter-spacing: 0.5px; }
        .footer .ref { display: inline; }
        .footer .site { float: right; font-weight: 700; color: #6B7280; }
    </style>
</head>
<body>
<div class="placarde">

    <div class="header">
        <span class="transaction">{{ $ad->transaction_type === 'vente' ? 'À vendre' : 'À louer' }}</span>
        <div class="brand">KEYHOME</div>
        <div class="brand-rule"></div>
    </div>

    @if(!empty($coverImage))
        <div class="photo" style="background-image: url('{{ $coverImage }}');"></div>
    @else
        <div class="photo"><div class="photo-placeholder">Pas de photo disponible</div></div>
    @endif

    <div class="info">
        <div class="price-row">
            <span class="price">{{ number_format((float) $ad->price, 0, ',', ' ') }} FCFA</span>@if($ad->transaction_type !== 'vente')<span class="price-suffix">/ mois</span>@endif
        </div>

        <div class="title">{{ \Illuminate\Support\Str::limit($ad->title, 60) }}</div>

        <div class="address">
            {{ $ad->adresse }}@if(!empty($quarter)), {{ $quarter }}@endif@if(!empty($city)), {{ $city }}@endif
        </div>

        <table class="features">
            <tr>
                <td>
                    <div class="feature-value">{{ $ad->bedrooms }}</div>
                    <div class="feature-label">Chambres</div>
                </td>
                <td>
                    <div class="feature-value">{{ $ad->bathrooms }}</div>
                    <div class="feature-label">Salles de bain</div>
                </td>
                <td>
                    <div class="feature-value">{{ (int) $ad->surface_area }} m²</div>
                    <div class="feature-label">Surface</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="qr-block">
        <table class="qr-row">
            <tr>
                <td class="qr-cell">
                    <img class="qr-img" src="{{ $qrDataUri }}" alt="QR Code">
                </td>
                <td class="qr-text">
                    <div class="qr-cta">Scannez pour <span class="accent">visiter</span></div>
                    <div class="qr-instruction">Toutes les infos et le contact du bailleur sur keyhome.app</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <span class="ref">Réf. {{ strtoupper(substr($ad->id, 0, 8)) }}</span>
        <span class="site">keyhome.app</span>
    </div>
</div>

<script>
    // Auto-fit the A5 sheet inside the iframe viewport (both axes) while
    // keeping the physical mm dimensions intact, so the preview is a faithful
    // 1:1 representation of the printable PDF — and renders on iOS, where a
    // blob: PDF in an <iframe> stays blank.
    (function () {
        const sheet = document.querySelector('.placarde');
        if (!sheet) return;
        const pxPerMm = 96 / 25.4;
        const sheetW = 148 * pxPerMm;
        const sheetH = 210 * pxPerMm;
        const margin = 16;
        function fit() {
            const availW = Math.max(50, window.innerWidth  - margin * 2);
            const availH = Math.max(50, window.innerHeight - margin * 2);
            const scale = Math.min(availW / sheetW, availH / sheetH, 2);
            sheet.style.transform = 'scale(' + scale.toFixed(3) + ')';
        }
        fit();
        window.addEventListener('resize', fit);
    })();
</script>
</body>
</html>
