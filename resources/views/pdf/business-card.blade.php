<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Carte de visite — {{ $user->firstname }} {{ $user->lastname }}</title>
    {{--
        Standard 90 × 55 mm landscape business card (single-page).
        DomPDF rules: tables for layout, absolute positioning, base64 assets.
    --}}
    <style>
        @page { size: 90mm 55mm; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #0F172A;
            background: #FFFFFF;
        }

        /* Slight reduction (89.6 × 54.6) absorbs the 0.5pt border without
           triggering DomPDF's overflow-to-page-2 quirk. */
        .card {
            width: 89.6mm;
            height: 54.6mm;
            position: relative;
            background: #FFFFFF;
            border-radius: 3mm;
            border: 0.5pt solid #CBD5E1;
        }

        .accent-bar {
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 3mm;
            background: #F6475F;
        }

        /* === Top header (avatar + name + KeyHome badge) === */
        .header {
            position: absolute;
            top: 4mm; left: 6mm; right: 4mm;
            height: 18mm;
        }

        .avatar {
            position: absolute;
            top: 0; left: 0;
            width: 16mm; height: 16mm;
            border-radius: 50%;
            background: #F1F5F9;
            border: 0.5pt solid #E2E8F0;
            overflow: hidden;
            text-align: center;
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .avatar .initials {
            display: block;
            font-size: 14pt; font-weight: 800;
            color: #F6475F;
            padding-top: 4mm;
            letter-spacing: 0.5px;
        }

        .name-block {
            position: absolute;
            top: 1mm; left: 19mm; right: 14mm;
        }
        .name {
            font-size: 11pt; font-weight: 800;
            line-height: 1.1; color: #0F172A;
            margin-bottom: 0.4mm;
        }
        .role {
            font-size: 7pt; color: #64748B;
            font-weight: 500; line-height: 1.2;
            margin-bottom: 0.4mm;
        }
        .brand-line {
            font-size: 7pt; color: #94A3B8; line-height: 1.2;
        }

        .kh-badge {
            position: absolute;
            top: 0; right: 0;
            width: 12mm; height: 12mm;
            border-radius: 50%;
            background: #FFE9EC;
            text-align: center;
            border: 0.5pt solid rgba(246,71,95,0.25);
        }
        .kh-badge img { width: 8mm; height: 8mm; margin-top: 1.7mm; }

        /* === Bottom-left: contact list === */
        .contact {
            position: absolute;
            bottom: 4mm; left: 6mm;
            width: 50mm;
        }
        .contact .row {
            font-size: 7.5pt; color: #334155;
            line-height: 1.45;
            display: block;
            margin-bottom: 0.6mm;
            white-space: nowrap; overflow: hidden;
        }
        .contact .row .ico {
            display: inline-block; width: 4mm;
            color: #F6475F; font-weight: 800;
        }
        .contact .row strong { color: #0F172A; font-weight: 700; }

        /* === Bottom-right: QR + CTA === */
        .qr-wrap {
            position: absolute;
            bottom: 3mm; right: 4mm;
            width: 24mm;
            text-align: center;
        }
        .qr-wrap img {
            width: auto; height: 22mm;
            display: block;
            margin: 0 auto 0.8mm auto;
        }
        .qr-wrap .qr-cta {
            font-size: 5.5pt; color: #64748B;
            line-height: 1.2; letter-spacing: 0.2px;
        }
        .qr-wrap .qr-cta strong { color: #F6475F; font-weight: 800; }
    </style>
</head>
<body>
<div class="card">
    <div class="accent-bar"></div>

    <div class="header">
        <div class="avatar">
            @if($avatarDataUri)
                <img src="{{ $avatarDataUri }}" alt="">
            @else
                <span class="initials">{{ strtoupper(substr($user->firstname, 0, 1)) }}{{ strtoupper(substr($user->lastname, 0, 1)) }}</span>
            @endif
        </div>

        <div class="name-block">
            <div class="name">{{ \Illuminate\Support\Str::limit($user->firstname.' '.$user->lastname, 28) }}</div>
            <div class="role">{{ $roleLabel }}</div>
            <div class="brand-line">KeyHome &nbsp;·&nbsp; {{ $adsCount > 0 ? $adsCount.' annonce'.($adsCount > 1 ? 's' : '').' active'.($adsCount > 1 ? 's' : '') : 'keyhome.app' }}</div>
        </div>

        <div class="kh-badge">
            @if(file_exists(base_path('keyhome-frontend-next/public/icons/icon-128x128.png')))
                <img src="data:image/png;base64,{{ base64_encode((string) file_get_contents(base_path('keyhome-frontend-next/public/icons/icon-128x128.png'))) }}" alt="KeyHome">
            @endif
        </div>
    </div>

    <div class="contact">
        @if(!empty($user->phone_number))
            <span class="row"><span class="ico">☎</span> {{ $user->phone_number }}</span>
        @endif
        @if(!empty($whatsappNumber))
            <span class="row"><span class="ico">▶</span> wa.me/{{ $whatsappNumber }}</span>
        @endif
        <span class="row"><span class="ico">↗</span> keyhome.app/<strong>{{ \Illuminate\Support\Str::limit($user->username ?: substr($user->id, 0, 8), 14, '…') }}</strong></span>
    </div>

    <div class="qr-wrap">
        <img src="{{ $qrDataUri }}" alt="QR Code">
        <div class="qr-cta">Scannez · <strong>mes annonces</strong></div>
    </div>
</div>
</body>
</html>
