{{-- PWA Splash Screen — shown only on the very first load of the session --}}
<div id="pwa-splash" style="display:none">
    <style>
        #pwa-splash {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
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
            animation: splashPulse 2s ease-in-out infinite;
            margin-bottom: 1.5rem;
        }
        #pwa-splash .splash-brand {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 2rem;
            font-weight: 800;
            color: #F6475F;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }
        #pwa-splash .splash-tagline {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 0.875rem;
            color: #94a3b8;
            letter-spacing: 0.05em;
        }
        #pwa-splash .splash-spinner {
            margin-top: 2.5rem;
            width: 36px;
            height: 36px;
            border: 3px solid rgba(246, 71, 95, 0.2);
            border-top-color: #F6475F;
            border-radius: 50%;
            animation: splashSpin 0.8s linear infinite;
        }
        @keyframes splashPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.9; }
        }
        @keyframes splashSpin {
            to { transform: rotate(360deg); }
        }
    </style>

    <img src="/pwa/icons/icon-192x192.png" alt="KeyHome" class="splash-logo">
    <div class="splash-brand">KeyHome</div>
    <div class="splash-tagline">Gestion Immobilière</div>
    <div class="splash-spinner"></div>
</div>
<script>
    (function() {
        const splash = document.getElementById('pwa-splash');
        if (!splash) return;

        // Only show splash once per browser session
        if (sessionStorage.getItem('pwa-splash-shown')) {
            splash.remove();
            return;
        }

        // Mark as shown for this session
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
                setTimeout(() => splash.remove(), 400);
            }, delay);
        }

        if (document.readyState === 'complete') {
            hideSplash();
        } else {
            window.addEventListener('load', hideSplash);
        }

        // Safety timeout — never stay stuck
        setTimeout(() => {
            if (document.getElementById('pwa-splash')) {
                hideSplash();
            }
        }, MAX_DISPLAY);
    })();
</script>
