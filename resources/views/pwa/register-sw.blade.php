{{-- PWA Service Worker Registration, Push Subscription & Re-Auth --}}
<script>
(function() {
    'use strict';

    const PWA = {
        vapidPublicKey: '{{ config("webpush.vapid.public_key", "") }}',
        csrfToken: '{{ csrf_token() }}',
        subscribeUrl: '/api/v1/pwa/push/subscribe',
        unsubscribeUrl: '/api/v1/pwa/push/unsubscribe',
        isStandalone: window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true,
    };

    // ─── Service Worker Registration ──────────────────────────
    async function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) return null;

        try {
            const registration = await navigator.serviceWorker.register('/sw.js', {
                scope: '/',
                updateViaCache: 'none',
            });

            // Check for updates immediately, then periodically (every 60 min)
            registration.update();
            setInterval(() => registration.update(), 60 * 60 * 1000);

            // Listen for new SW waiting
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                if (!newWorker) return;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // New SW installed while old one is still active — reload to activate
                        console.log('[PWA] New version installed, reloading...');
                        window.location.reload();
                    }
                });
            });

            console.log('[PWA] Service Worker registered:', registration.scope);
            return registration;
        } catch (error) {
            console.error('[PWA] Service Worker registration failed:', error);
            return null;
        }
    }

    // ─── Push Notification Subscription ───────────────────────
    async function subscribePush(registration) {
        if (!('PushManager' in window) || !PWA.vapidPublicKey) return;

        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.log('[PWA] Push notification permission denied');
                return;
            }

            let subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                const applicationServerKey = urlBase64ToUint8Array(PWA.vapidPublicKey);
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: applicationServerKey,
                });
            }

            // Send subscription to server
            await fetch(PWA.subscribeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': PWA.csrfToken,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    keys: {
                        p256dh: btoa(String.fromCharCode.apply(null,
                            new Uint8Array(subscription.getKey('p256dh')))),
                        auth: btoa(String.fromCharCode.apply(null,
                            new Uint8Array(subscription.getKey('auth')))),
                    },
                }),
            });

            console.log('[PWA] Push subscription sent to server');
        } catch (error) {
            console.error('[PWA] Push subscription failed:', error);
        }
    }

    // ─── Install Prompt Handling ──────────────────────────────
    let deferredInstallPrompt = null;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredInstallPrompt = e;
        showInstallBanner();
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        hideInstallBanner();
        console.log('[PWA] App installed successfully');
    });

    // ─── Mandatory Re-Authentication on Launch ────────────────
    function enforceReAuth() {
        if (!PWA.isStandalone) return;

        const SESSION_KEY = 'keyhome_pwa_session_active';

        // On visibility change (app comes back from background)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                const sessionActive = sessionStorage.getItem(SESSION_KEY);
                if (!sessionActive) {
                    // Session lost (app was killed or cold start) — force re-auth
                    forceReAuth();
                }
            }
        });

        // On page show (back/forward cache restoration = bfcache)
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                // Page restored from bfcache
                const sessionActive = sessionStorage.getItem(SESSION_KEY);
                if (!sessionActive) {
                    forceReAuth();
                }
            }
        });

        // Mark session as active on successful page load
        sessionStorage.setItem(SESSION_KEY, Date.now().toString());

        // On cold start: Check if session cookie is still valid
        validateSession();
    }

    async function validateSession() {
        try {
            const response = await fetch('/api/v1/pwa/session/validate', {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok || response.status === 401) {
                forceReAuth();
            }
        } catch {
            // Network error — allow offline access if cached
        }
    }

    function forceReAuth() {
        sessionStorage.removeItem('keyhome_pwa_session_active');

        // Determine current panel login page
        const path = window.location.pathname;
        let loginUrl = '/admin/login';

        if (path.startsWith('/agency')) {
            loginUrl = '/agency/login';
        } else if (path.startsWith('/owner')) {
            loginUrl = '/owner/login';
        }

        // Avoid redirect loop
        if (!path.endsWith('/login')) {
            window.location.href = loginUrl;
        }
    }

    // ─── Update Banner ────────────────────────────────────────
    function showUpdateBanner() {
        if (document.getElementById('pwa-update-banner')) return;

        const banner = document.createElement('div');
        banner.id = 'pwa-update-banner';
        banner.innerHTML = `
            <style>
                #pwa-update-banner {
                    position: fixed;
                    bottom: 1rem;
                    left: 50%;
                    transform: translateX(-50%);
                    background: #1e293b;
                    color: #f1f5f9;
                    padding: 0.75rem 1.25rem;
                    border-radius: 0.75rem;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
                    z-index: 99998;
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    font-family: 'Poppins', sans-serif;
                    font-size: 0.875rem;
                    animation: slideUp 0.3s ease;
                }
                #pwa-update-banner button {
                    background: #F6475F;
                    color: white;
                    border: none;
                    padding: 0.4rem 1rem;
                    border-radius: 0.5rem;
                    font-weight: 600;
                    cursor: pointer;
                    white-space: nowrap;
                    font-family: inherit;
                }
                @keyframes slideUp {
                    from { transform: translateX(-50%) translateY(100%); opacity: 0; }
                    to { transform: translateX(-50%) translateY(0); opacity: 1; }
                }
            </style>
            <span>Nouvelle version disponible</span>
            <button onclick="window.location.reload()">Mettre à jour</button>
        `;
        document.body.appendChild(banner);
    }

    // ─── Install Banner ───────────────────────────────────────
    function showInstallBanner() {
        if (document.getElementById('pwa-install-banner') || PWA.isStandalone) return;

        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.innerHTML = `
            <style>
                #pwa-install-banner {
                    position: fixed;
                    bottom: 1rem;
                    left: 50%;
                    transform: translateX(-50%);
                    background: #1e293b;
                    color: #f1f5f9;
                    padding: 0.75rem 1.25rem;
                    border-radius: 0.75rem;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
                    z-index: 99997;
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    font-family: 'Poppins', sans-serif;
                    font-size: 0.875rem;
                    animation: slideUp 0.3s ease;
                    max-width: 90vw;
                }
                #pwa-install-banner button {
                    border: none;
                    padding: 0.4rem 1rem;
                    border-radius: 0.5rem;
                    font-weight: 600;
                    cursor: pointer;
                    white-space: nowrap;
                    font-family: inherit;
                }
                #pwa-install-banner .install-btn { background: #F6475F; color: white; }
                #pwa-install-banner .dismiss-btn { background: #334155; color: #94a3b8; }
            </style>
            <span>Installer KeyHome sur cet appareil ?</span>
            <button class="install-btn" id="pwa-install-accept">Installer</button>
            <button class="dismiss-btn" id="pwa-install-dismiss">✕</button>
        `;
        document.body.appendChild(banner);

        document.getElementById('pwa-install-accept').addEventListener('click', async () => {
            if (deferredInstallPrompt) {
                deferredInstallPrompt.prompt();
                const result = await deferredInstallPrompt.userChoice;
                console.log('[PWA] Install prompt result:', result.outcome);
                deferredInstallPrompt = null;
            }
            hideInstallBanner();
        });

        document.getElementById('pwa-install-dismiss').addEventListener('click', hideInstallBanner);
    }

    function hideInstallBanner() {
        const banner = document.getElementById('pwa-install-banner');
        if (banner) banner.remove();
    }

    // ─── Utilities ────────────────────────────────────────────
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // ─── Online/Offline Indicator ─────────────────────────────
    function setupConnectivityIndicator() {
        function updateStatus() {
            const existing = document.getElementById('pwa-offline-indicator');
            if (navigator.onLine) {
                if (existing) existing.remove();
            } else if (!existing) {
                const indicator = document.createElement('div');
                indicator.id = 'pwa-offline-indicator';
                indicator.innerHTML = `
                    <style>
                        #pwa-offline-indicator {
                            position: fixed;
                            top: 0;
                            left: 0;
                            right: 0;
                            background: #ef4444;
                            color: white;
                            text-align: center;
                            padding: 0.35rem;
                            font-size: 0.8rem;
                            font-family: 'Poppins', sans-serif;
                            z-index: 99999;
                            font-weight: 500;
                        }
                    </style>
                    Hors ligne — certaines fonctionnalités peuvent être limitées
                `;
                document.body.prepend(indicator);
            }
        }
        window.addEventListener('online', updateStatus);
        window.addEventListener('offline', updateStatus);
        updateStatus();
    }

    // ─── Bootstrap ────────────────────────────────────────────
    async function init() {
        const registration = await registerServiceWorker();

        if (registration) {
            // Delay push subscription to avoid permission prompt on first visit
            setTimeout(() => subscribePush(registration), 5000);
        }

        enforceReAuth();
        setupConnectivityIndicator();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
