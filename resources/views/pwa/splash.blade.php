{{-- PWA Splash Screen — shown only on the very first load of the session --}}
<div id="pwa-splash" style="display:none" role="status" aria-live="polite" aria-busy="true" aria-label="Chargement de KeyHome">
    <style>
        #pwa-splash {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            color: #111827;
            font-family: ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }
        #pwa-splash.fade-out {
            opacity: 0;
            visibility: hidden;
        }
        #pwa-splash .splash-logo {
            width: 96px;
            height: 96px;
            border-radius: 24px;
            margin-bottom: 1.5rem;
        }
        @keyframes splashPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.04); opacity: 0.95; }
        }
        @media (prefers-reduced-motion: no-preference) {
            #pwa-splash .splash-logo {
                animation: splashPulse 2s ease-in-out infinite;
            }
        }
        #pwa-splash .splash-brand {
            font-size: 2rem;
            font-weight: 800;
            color: #e11d48;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }
        #pwa-splash .splash-tagline {
            font-size: 0.875rem;
            color: #6b7280;
            letter-spacing: 0.02em;
        }
        #pwa-splash .splash-spinner {
            margin-top: 2.5rem;
            width: 36px;
            height: 36px;
            border: 3px solid rgb(225 29 72 / 0.2);
            border-top-color: #e11d48;
            border-radius: 50%;
        }
        @media (prefers-reduced-motion: no-preference) {
            #pwa-splash .splash-spinner {
                animation: splashSpin 0.8s linear infinite;
            }
        }
        @keyframes splashSpin {
            to { transform: rotate(360deg); }
        }
    </style>

    <img src="/pwa/icons/icon-192x192.png" alt="" class="splash-logo">
    <div class="splash-brand">KeyHome</div>
    <div class="splash-tagline">Gestion Immobilière</div>
    <div class="splash-spinner" aria-hidden="true"></div>
</div>
<script>
    (function() {
        const splash = document.getElementById('pwa-splash');
        if (!splash) return;

        if (sessionStorage.getItem('pwa-splash-shown')) {
            splash.remove();
            return;
        }

        sessionStorage.setItem('pwa-splash-shown', '1');
        splash.style.display = 'flex';

        const showTime = Date.now();
        const MIN_DISPLAY = 1200;
        const MAX_DISPLAY = 4000;

        function hideSplash() {
            const elapsed = Date.now() - showTime;
            const delay = Math.max(0, MIN_DISPLAY - elapsed);
            setTimeout(() => {
                splash.classList.add('fade-out');
                splash.removeAttribute('aria-busy');
                setTimeout(() => splash.remove(), 400);
            }, delay);
        }

        if (document.readyState === 'complete') {
            hideSplash();
        } else {
            window.addEventListener('load', hideSplash);
        }

        setTimeout(() => {
            if (document.getElementById('pwa-splash')) {
                hideSplash();
            }
        }, MAX_DISPLAY);
    })();
</script>
