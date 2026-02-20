/**
 * Filament Native Bridge
 * Améliore l'interaction entre Filament (WebView) et l'application Native (React Native)
 */
(function() {
    const isNative = window.ReactNativeWebView || window.location.search.includes('app_mode=native');
    
    if (!isNative) return;

    document.body.classList.add('is-native-app');

    // Helper pour envoyer des messages au natif
    window.sendToNative = function(type, data = {}) {
        if (window.ReactNativeWebView) {
            window.ReactNativeWebView.postMessage(JSON.stringify({ type, data }));
        }
    };

    // Intercepter les clics sur les inputs de type 'tel' pour ouvrir le clavier natif optimisé
    document.addEventListener('focusin', (e) => {
        if (e.target.tagName === 'INPUT' && e.target.type === 'tel') {
            window.sendToNative('FOCUS_TEL_INPUT', { name: e.target.name });
        }
    });

    // Intercepter les ouvertures de modales/slide-overs pour notifier le natif (gestion du bouton retour)
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.addedNodes.length) {
                mutation.addedNodes.forEach((node) => {
                    if (node.classList && (node.classList.contains('fi-modal') || node.classList.contains('fi-slide-over'))) {
                        window.sendToNative('MODAL_OPENED');
                    }
                });
            }
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Notification de chargement terminé
    window.sendToNative('PAGE_LOADED', { url: window.location.href });
})();
