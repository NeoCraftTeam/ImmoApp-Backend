<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Aperçu — Carte de visite</title>
    {{--
        Browser-friendly preview of the business card. Re-uses the EXACT same
        DOM/styles as the printable @see pdf.business-card view so the on-screen
        preview is pixel-faithful with the downloaded PDF. Differences:
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

        .card {
            width: 90mm; height: 55mm; position: relative; background: #FFFFFF;
            border-radius: 3mm; border: 0.5pt solid #E5E7EB;
            box-shadow: 0 16px 40px -16px rgba(17, 24, 39, 0.18);
            transform-origin: center center; transform: scale(1);
        }

        .header { position: absolute; top: 5mm; left: 6mm; right: 5mm; height: 17mm; }

        .avatar {
            position: absolute; top: 0; left: 0; width: 15mm; height: 15mm;
            border-radius: 50%;
            background-color: #F3F4F6; background-position: center; background-size: cover;
            background-repeat: no-repeat;
            border: 0.5pt solid #E5E7EB; overflow: hidden; text-align: center;
        }
        .avatar .initials { display: block; font-size: 13pt; font-weight: 800; color: #0D9488; padding-top: 4mm; letter-spacing: 0.5px; }

        .name-block { position: absolute; top: 0.5mm; left: 19mm; right: 12mm; }
        .name { font-size: 11pt; font-weight: 800; line-height: 1.1; color: #111827; margin-bottom: 0.6mm; }
        .role { font-size: 7pt; color: #6B7280; font-weight: 600; line-height: 1.2; }
        .brand-rule { width: 10mm; height: 0.8mm; background: #0D9488; margin: 1mm 0; }
        .brand-line { font-size: 6.5pt; color: #9CA3AF; line-height: 1.2; letter-spacing: 0.5px; }
        .brand-line strong { color: #6B7280; font-weight: 700; }

        .kh-badge {
            position: absolute; top: 0; right: 0; width: 11mm; height: 11mm;
            border-radius: 50%; border: 0.5pt solid #E5E7EB; text-align: center;
        }
        .kh-badge img { width: 7mm; height: 7mm; margin-top: 1.8mm; }

        .contact { position: absolute; bottom: 4mm; left: 6mm; width: 52mm; }
        .contact .row {
            font-size: 7.5pt; color: #374151; line-height: 1.5;
            display: block; margin-bottom: 0.6mm; white-space: nowrap; overflow: hidden;
        }
        .contact .row .ico { display: inline-block; width: 4mm; color: #0D9488; font-weight: 800; }
        .contact .row strong { color: #111827; font-weight: 700; }

        .qr-wrap { position: absolute; bottom: 3.5mm; right: 5mm; width: 22mm; text-align: center; }
        .qr-wrap img { width: auto; height: 20mm; display: block; margin: 0 auto 0.8mm auto; }
        .qr-wrap .qr-cta { font-size: 5.5pt; color: #6B7280; line-height: 1.2; letter-spacing: 0.2px; }
        .qr-wrap .qr-cta strong { color: #0D9488; font-weight: 800; }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        @if($avatarDataUri)
            <div class="avatar" style="background-image: url('{{ $avatarDataUri }}');"></div>
        @else
            <div class="avatar"><span class="initials">{{ strtoupper(substr($user->firstname, 0, 1)) }}{{ strtoupper(substr($user->lastname, 0, 1)) }}</span></div>
        @endif

        <div class="name-block">
            <div class="name">{{ \Illuminate\Support\Str::limit($user->firstname.' '.$user->lastname, 28) }}</div>
            <div class="role">{{ $roleLabel }}</div>
            <div class="brand-rule"></div>
            <div class="brand-line">KEYHOME &nbsp;·&nbsp; {{ $adsCount > 0 ? $adsCount.' annonce'.($adsCount > 1 ? 's' : '').' active'.($adsCount > 1 ? 's' : '') : 'keyhome.app' }}</div>
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

<script>
    // Auto-fit the card inside the iframe viewport (both axes) while keeping
    // the physical mm dimensions intact, so the preview is a faithful 1:1
    // representation of the printable PDF.
    (function () {
        const card = document.querySelector('.card');
        if (!card) return;
        const pxPerMm = 96 / 25.4;
        const cardW = 90 * pxPerMm;
        const cardH = 55 * pxPerMm;
        const margin = 12;
        function fit() {
            const availW = Math.max(50, window.innerWidth  - margin * 2);
            const availH = Math.max(50, window.innerHeight - margin * 2);
            const scale = Math.min(availW / cardW, availH / cardH, 4);
            card.style.transform = 'scale(' + scale.toFixed(3) + ')';
        }
        fit();
        window.addEventListener('resize', fit);
    })();
</script>
</body>
</html>
