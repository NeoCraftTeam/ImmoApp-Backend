<script>
(function () {
    'use strict';

    // Admin panel must never be controlled by a stale PWA service worker.
    // This prevents phantom 404 on Livewire navigation (e.g. /surveys/create).
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const marker = 'kh_admin_sw_unregistered_v1';
    const isAdminHost = window.location.hostname.startsWith('admin.');

    if (!isAdminHost) {
        return;
    }

    (async function unregisterAll() {
        try {
            const registrations = await navigator.serviceWorker.getRegistrations();
            await Promise.all(registrations.map((registration) => registration.unregister()));

            if ('caches' in window) {
                const keys = await caches.keys();
                await Promise.all(keys.map((key) => caches.delete(key)));
            }

            if (!sessionStorage.getItem(marker)) {
                sessionStorage.setItem(marker, '1');
                window.location.reload();
            }
        } catch (error) {
            console.warn('[PWA] Unable to unregister service workers on admin panel:', error);
        }
    })();
})();
</script>
