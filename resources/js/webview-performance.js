/**
 * WebView Performance Optimization
 * Optimise les performances des WebViews en mode natif
 */
(function() {
    const isNative = window.ReactNativeWebView || window.location.search.includes('app_mode=native');
    
    if (!isNative) return;

    // ──────────────────────────────────────────────
    // 1. OPTIMISATION DU RENDU
    // ──────────────────────────────────────────────

    // Désactiver les animations inutiles en mode natif
    document.documentElement.style.setProperty('--animation-duration', '0.1s');
    
    // Utiliser le GPU pour les animations
    document.documentElement.style.setProperty('will-change', 'transform');

    // ──────────────────────────────────────────────
    // 2. LAZY LOADING DES IMAGES
    // ──────────────────────────────────────────────

    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach((img) => {
            imageObserver.observe(img);
        });
    }

    // ──────────────────────────────────────────────
    // 3. DEBOUNCE DES ÉVÉNEMENTS DE SCROLL
    // ──────────────────────────────────────────────

    let scrollTimeout;
    window.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        document.body.classList.add('is-scrolling');
        
        scrollTimeout = setTimeout(() => {
            document.body.classList.remove('is-scrolling');
        }, 150);
    }, { passive: true });

    // ──────────────────────────────────────────────
    // 4. OPTIMISATION DES FORMULAIRES
    // ──────────────────────────────────────────────

    // Ajouter l'attribut autocomplete aux champs de formulaire
    document.querySelectorAll('input[type="text"]').forEach((input) => {
        if (!input.hasAttribute('autocomplete')) {
            input.setAttribute('autocomplete', 'off');
        }
    });

    document.querySelectorAll('input[type="email"]').forEach((input) => {
        if (!input.hasAttribute('autocomplete')) {
            input.setAttribute('autocomplete', 'email');
        }
    });

    document.querySelectorAll('input[type="tel"]').forEach((input) => {
        if (!input.hasAttribute('autocomplete')) {
            input.setAttribute('autocomplete', 'tel');
        }
    });

    // ──────────────────────────────────────────────
    // 5. OPTIMISATION DES CLICS
    // ──────────────────────────────────────────────

    // Réduire le délai de réponse au clic (300ms → 0ms)
    document.addEventListener('touchstart', function() {}, { passive: true });

    // ──────────────────────────────────────────────
    // 6. MONITORING DES PERFORMANCES
    // ──────────────────────────────────────────────

    if (window.performance && window.performance.timing) {
        window.addEventListener('load', () => {
            const timing = window.performance.timing;
            const navigationStart = timing.navigationStart;
            
            const metrics = {
                domContentLoaded: timing.domContentLoadedEventEnd - navigationStart,
                pageLoad: timing.loadEventEnd - navigationStart,
                firstPaint: timing.responseEnd - navigationStart,
            };

            // Envoyer les métriques au natif
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: 'PERFORMANCE_METRICS',
                    data: metrics,
                }));
            }
        });
    }

    // ──────────────────────────────────────────────
    // 7. GESTION DE LA MÉMOIRE
    // ──────────────────────────────────────────────

    // Nettoyer les listeners inutilisés
    window.addEventListener('beforeunload', () => {
        // Supprimer les références circulaires
        document.querySelectorAll('[data-cleanup]').forEach((el) => {
            el.remove();
        });
    });

    console.log('[WebView] Performance optimizations loaded');
})();
