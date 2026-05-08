<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Aperçu — Carte de visite</title>
    {{--
        Browser-friendly preview of the business card.

        Re-uses the EXACT same DOM structure and styles as the printable
        @see pdf.business-card view so the on-screen preview is pixel-perfect
        with the downloaded PDF. The only differences are:
          • a transparent surrounding viewport so the host page can centre it,
          • a CSS scale that adapts to the iframe's available size (both axes),
          • a fallback web font (DomPDF embeds DejaVu Sans).
    --}}
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html {
            height: 100%;
            width: 100%;
        }
        body {
            background: transparent;
            font-family: -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #0F172A;
            margin: 0;
            width: 100%;
            height: 100%;
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            box-sizing: border-box;
            overflow: hidden;
        }

        .scale-wrap {
            display: block;
            width: max-content;
            height: max-content;
            transform-origin: center center;
            flex: 0 0 auto;
        }

        .card {
            width: 90mm;
            height: 55mm;
            position: relative;
            background: #FFFFFF;
            border-radius: 3mm;
            border: 0.5pt solid #E2E8F0;
            box-shadow: 0 16px 40px -16px rgba(15, 23, 42, 0.18);
        }

        .accent-bar {
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 3mm;
            background: #F6475F;
            border-radius: 3mm 0 0 3mm;
        }

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
            font-weight: 500; margin-bottom: 0.4mm;
        }
        .brand-line { font-size: 7pt; color: #94A3B8; }

        .kh-badge {
            position: absolute;
            top: 0; right: 0;
            border: none;
            background: transparent;
            padding: 0;
            margin: 0;
            line-height: 0;
        }
        .kh-badge img {
            width: 8mm;
            height: 8mm;
            margin: 0;
            display: block;
            border: 0;
            outline: none;
            box-shadow: none;
            object-fit: contain;
        }

        .contact {
            position: absolute;
            bottom: 4mm; left: 6mm;
            width: 50mm;
        }
        .contact .row {
            font-size: 7.5pt; color: #334155;
            line-height: 1.45;
            display: block; margin-bottom: 0.6mm;
            white-space: nowrap; overflow: hidden;
        }
        .contact .row .ico {
            display: inline-block; width: 4mm;
            color: #F6475F; font-weight: 800;
        }
        .contact .row strong { color: #0F172A; font-weight: 700; }

        .qr-wrap {
            position: absolute;
            bottom: 3mm; right: 4mm;
            width: 24mm; text-align: center;
        }
        .qr-wrap img {
            width: 22mm; height: 22mm;
            display: block;
            margin: 0 auto 0.8mm auto;
        }
        .qr-wrap .qr-cta {
            font-size: 5.5pt; color: #64748B; line-height: 1.2;
        }
        .qr-wrap .qr-cta strong { color: #F6475F; font-weight: 800; }
    </style>
</head>
<body>
<div class="scale-wrap">
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
            @php
                $businessCardLogoPath = public_path('images/keyhomelogo_transparent.png');
                if (! is_file($businessCardLogoPath)) {
                    $businessCardLogoPath = public_path('images/keyhomelogo.png');
                }
            @endphp
            @if(is_file($businessCardLogoPath))
                <img src="data:image/png;base64,{{ base64_encode((string) file_get_contents($businessCardLogoPath)) }}" alt="KeyHome">
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
    </div>
</div>
</div>

<script>
    // Auto-fit the card inside the iframe viewport (both axes) while
    // keeping the physical mm dimensions intact, so the preview is a
    // faithful 1:1 representation of the printable PDF.
    //
    // srcDoc iframes often report window.innerWidth/Height ≈ 300×150 on the
    // first synchronous run — use clientWidth/Height on documentElement plus
    // ResizeObserver + rAF so the scale matches the real host box.
    (function () {
        const wrap = document.querySelector('.scale-wrap');
        if (!wrap) return;
        const pxPerMm = 96 / 25.4;
        const cardW = 90 * pxPerMm;
        const cardH = 55 * pxPerMm;
        const margin = 12;
        function viewportBox() {
            const docEl = document.documentElement;
            const w = Math.max(
                window.innerWidth || 0,
                docEl.clientWidth || 0,
                docEl.getBoundingClientRect().width || 0
            );
            const h = Math.max(
                window.innerHeight || 0,
                docEl.clientHeight || 0,
                docEl.getBoundingClientRect().height || 0
            );
            return { w, h };
        }
        function fit() {
            const box = viewportBox();
            const availW = Math.max(50, box.w - margin * 2);
            const availH = Math.max(50, box.h - margin * 2);
            const scale = Math.min(availW / cardW, availH / cardH);
            wrap.style.transform = 'scale(' + scale.toFixed(3) + ')';
        }
        function fitSoon() {
            requestAnimationFrame(function () {
                requestAnimationFrame(fit);
            });
        }
        fitSoon();
        window.addEventListener('resize', fit);
        if (typeof ResizeObserver !== 'undefined') {
            const ro = new ResizeObserver(function () {
                fit();
            });
            ro.observe(document.documentElement);
        }
        window.__khBusinessCardPreviewRefit = fit;
    })();
</script>
</body>
</html>
