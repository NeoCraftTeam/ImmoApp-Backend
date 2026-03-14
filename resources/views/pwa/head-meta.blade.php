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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

{{-- Apple Touch Icons --}}
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="152x152" href="/pwa/icons/icon-152x152.png">
<link rel="apple-touch-icon" sizes="192x192" href="/pwa/icons/icon-192x192.png">
<link rel="apple-touch-icon" sizes="512x512" href="/pwa/icons/icon-512x512.png">

{{-- Favicons --}}
<link rel="icon" type="image/png" sizes="32x32" href="/pwa/icons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/pwa/icons/favicon-16x16.png">

{{-- Apple Splash Screens (iOS standalone mode) --}}
{{-- iPhone SE (1st gen) 640x1136 --}}
<link rel="apple-touch-startup-image" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2)" href="/pwa/icons/icon-512x512.png">
{{-- iPhone 8 / SE (2nd gen) 750x1334 --}}
<link rel="apple-touch-startup-image" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)" href="/pwa/icons/icon-512x512.png">
{{-- iPhone 14 / 15 / X / XS 1170x2532 --}}
<link rel="apple-touch-startup-image" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)" href="/pwa/icons/icon-512x512.png">
{{-- iPhone 14 Plus / 15 Plus / XR / 11 828x1792 --}}
<link rel="apple-touch-startup-image" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2)" href="/pwa/icons/icon-512x512.png">
{{-- iPhone 14 Pro / 15 Pro 1179x2556 --}}
<link rel="apple-touch-startup-image" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3)" href="/pwa/icons/icon-512x512.png">
{{-- iPhone 14 Pro Max / 15 Pro Max 1290x2796 --}}
<link rel="apple-touch-startup-image" media="(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3)" href="/pwa/icons/icon-512x512.png">
{{-- iPad (9th gen) 1536x2048 --}}
<link rel="apple-touch-startup-image" media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2)" href="/pwa/icons/icon-512x512.png">
{{-- iPad Pro 11" 1668x2388 --}}
<link rel="apple-touch-startup-image" media="(device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2)" href="/pwa/icons/icon-512x512.png">
{{-- iPad Pro 12.9" 2048x2732 --}}
<link rel="apple-touch-startup-image" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2)" href="/pwa/icons/icon-512x512.png">
