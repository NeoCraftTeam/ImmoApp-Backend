<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Pancarte — {{ $ad->title }}</title>
    {{--
        A5 placarde — single-page, edge-to-edge design.

        DomPDF rules followed throughout:
          • Sections are absolutely positioned relative to the .placarde root
            so the QR block can NEVER be pushed off-page by content above.
          • Heights are in mm; horizontal layout uses tables (no flex/grid).
          • The QR is a pre-rasterised PNG embedded as a data URI.
    --}}
    <style>
        @page { size: A5 portrait; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #0F172A;
            background: #FFFFFF;
        }

        .placarde {
            width: 148mm;
            height: 210mm;
            position: relative;
            background: #FFFFFF;
        }

        /* === Top crimson band (8 mm) === */
        .top-bar {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 8mm;
            background: #F6475F;
            color: #FFFFFF;
        }
        .top-bar .brand {
            position: absolute;
            top: 1.8mm; left: 8mm;
            font-size: 9pt; font-weight: 800; letter-spacing: 2.2px;
        }
        .top-bar .tagline {
            position: absolute;
            top: 2.2mm; right: 8mm;
            font-size: 7.5pt; font-style: italic; opacity: 0.95;
        }

        /* === Bottom crimson band (6 mm) === */
        .bottom-bar {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 6mm;
            background: #F6475F;
            color: #FFFFFF;
            text-align: center;
            font-size: 7pt; font-weight: 600; letter-spacing: 1.5px;
            padding-top: 1.7mm;
            text-transform: uppercase;
        }
        .bottom-bar strong { font-weight: 800; letter-spacing: 2px; }

        /* === Hero photo === */
        .photo {
            position: absolute;
            top: 14mm; left: 8mm; right: 8mm;
            height: 45mm;
            background: #F1F5F9;
            border: 0.5pt solid #E2E8F0;
            border-radius: 2mm;
            overflow: hidden;
        }
        .photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .photo-placeholder {
            text-align: center; color: #CBD5E1;
            font-size: 9.5pt; font-weight: 600;
            padding-top: 19mm;
        }

        .status-pill {
            position: absolute;
            top: 18mm; right: 12mm;
            background: #F6475F; color: #FFFFFF;
            padding: 1.5mm 4mm;
            border-radius: 10mm;
            font-size: 7.5pt; font-weight: 800;
            letter-spacing: 1.5px; text-transform: uppercase;
        }

        /* === Title + price + meta + features === */
        .info {
            position: absolute;
            top: 65mm;
            left: 8mm; right: 8mm;
            height: 60mm;
        }
        .title {
            font-size: 14pt; font-weight: 800;
            line-height: 1.2; color: #0F172A;
            margin-bottom: 2.5mm;
        }
        .price {
            font-size: 22pt; font-weight: 900;
            color: #F6475F; line-height: 1; display: inline;
        }
        .price-suffix {
            font-size: 9pt; color: #64748B; font-weight: 600;
            display: inline; margin-left: 1.5mm;
        }
        .price-row { margin-bottom: 3mm; }
        .meta {
            font-size: 9pt; color: #475569;
            line-height: 1.4;
            padding-bottom: 3mm; margin-bottom: 3mm;
            border-bottom: 0.5pt dashed #CBD5E1;
        }
        .features { width: 100%; border-collapse: collapse; }
        .features td {
            text-align: center; padding: 1mm 0;
            border-right: 0.5pt solid #E2E8F0;
            width: 33.33%; vertical-align: middle;
        }
        .features td:last-child { border-right: none; }
        .feature-value {
            font-size: 13pt; font-weight: 800;
            color: #0F172A; line-height: 1.05;
        }
        .feature-label {
            font-size: 6.5pt; color: #94A3B8;
            text-transform: uppercase; letter-spacing: 1px;
            margin-top: 0.8mm;
        }

        /* === QR block — anchored above the bottom bar === */
        .qr-block {
            position: absolute;
            bottom: 6mm;
            left: 8mm; right: 8mm;
            height: 50mm;
            border-top: 0.5pt solid #E2E8F0;
            padding-top: 3mm;
        }
        .qr-row { width: 100%; border-collapse: collapse; }
        .qr-row td { vertical-align: middle; padding: 0; }
        .qr-cell { width: 44mm; text-align: center; }
        .qr-img { width: 42mm; height: 42mm; display: block; margin: 0 auto; }
        .qr-text { text-align: left; padding-left: 4mm !important; }
        .qr-cta {
            font-size: 13pt; font-weight: 800;
            color: #0F172A; line-height: 1.15;
            margin-bottom: 1.5mm;
        }
        .qr-cta .accent { color: #F6475F; }
        .qr-instruction {
            font-size: 8pt; color: #64748B; line-height: 1.55;
        }
        .qr-instruction strong { color: #F6475F; font-weight: 800; }
    </style>
</head>
<body>
<div class="placarde">

    <div class="top-bar">
        <span class="brand">KEYHOME</span>
        <span class="tagline">Votre patrimoine immobilier en poche</span>
    </div>

    <div class="photo">
        @if(!empty($coverImage))
            <img src="{{ $coverImage }}" alt="">
        @else
            <div class="photo-placeholder">Pas de photo disponible</div>
        @endif
    </div>

    <div class="status-pill">{{ $ad->transaction_type === 'vente' ? 'À VENDRE' : 'À LOUER' }}</div>

    <div class="info">
        <div class="title">{{ \Illuminate\Support\Str::limit($ad->title, 60) }}</div>

        <div class="price-row">
            <span class="price">{{ number_format((float) $ad->price, 0, ',', ' ') }} FCFA</span>@if($ad->transaction_type !== 'vente')<span class="price-suffix">/ mois</span>@endif
        </div>

        <div class="meta">
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
                    <div class="qr-instruction">
                        Photos HD · Visite 360°<br>
                        Contact direct du bailleur<br>
                        sur <strong>keyhome.app</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="bottom-bar">
        Annonce vérifiée &nbsp;·&nbsp; Réf. <strong>{{ strtoupper(substr($ad->id, 0, 8)) }}</strong> &nbsp;·&nbsp; <strong>KEYHOME.APP</strong>
    </div>
</div>
</body>
</html>
