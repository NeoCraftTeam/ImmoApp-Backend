{{-- PWA Head Meta Tags — injected via panels::head.end renderHook --}}
<link rel="manifest" href="/manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="{{ $themeColor ?? '#F6475F' }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="KeyHome">
<meta name="application-name" content="KeyHome">
<meta name="mobile-web-app-capable" content="yes">
<meta name="msapplication-TileColor" content="{{ $themeColor ?? '#F6475F' }}">
<meta name="msapplication-TileImage" content="/pwa/icons/icon-144x144.png">

{{-- Apple Touch Icons --}}
<link rel="apple-touch-icon" sizes="152x152" href="/pwa/icons/icon-152x152.png">
<link rel="apple-touch-icon" sizes="192x192" href="/pwa/icons/icon-192x192.png">
<link rel="apple-touch-icon" sizes="512x512" href="/pwa/icons/icon-512x512.png">

{{-- Apple Splash Screen (iOS) --}}
<meta name="apple-mobile-web-app-capable" content="yes">
