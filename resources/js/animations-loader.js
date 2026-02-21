/**
 * Animations & Loader Manager
 * Gère les animations fluides et les loaders pour les WebViews React Native
 */
(function() {
    const isNative = window.ReactNativeWebView || window.location.search.includes('app_mode=native');
    
    if (!isNative) return;

    // ──────────────────────────────────────────────
    // 1. GESTION DES LOADERS
    // ──────────────────────────────────────────────

    class LoaderManager {
        constructor() {
            this.loaders = new Map();
            this.defaultLoaderId = 'global-loader';
            
            this.init();
        }

        init() {
            // Créer un loader global
            this.createLoader(this.defaultLoaderId);
            
            // Intercepter les requêtes Fetch
            this.interceptFetch();
            
            // Intercepter les événements Livewire
            this.interceptLivewire();
            
            console.log('[LoaderManager] Initialized');
        }

        /**
         * Créer un loader.
         */
        createLoader(id) {
            const loader = document.createElement('div');
            loader.id = id;
            loader.className = 'native-loader';
            loader.innerHTML = `
                <div class="loader-overlay">
                    <div class="loader-spinner">
                        <div class="spinner"></div>
                        <p class="loader-text">Chargement...</p>
                    </div>
                </div>
            `;
            loader.style.display = 'none';
            document.body.appendChild(loader);
            
            this.loaders.set(id, { element: loader, count: 0 });
        }

        /**
         * Afficher un loader.
         */
        show(id = this.defaultLoaderId, message = 'Chargement...') {
            const loader = this.loaders.get(id);
            if (!loader) {
                this.createLoader(id);
                return this.show(id, message);
            }

            loader.count++;
            loader.element.style.display = 'flex';
            
            // Mettre à jour le message
            const textElement = loader.element.querySelector('.loader-text');
            if (textElement) {
                textElement.textContent = message;
            }

            // Notifier le natif
            this.notifyNative('LOADER_SHOWN', { id, message });
        }

        /**
         * Masquer un loader.
         */
        hide(id = this.defaultLoaderId) {
            const loader = this.loaders.get(id);
            if (!loader) return;

            loader.count--;
            
            if (loader.count <= 0) {
                loader.element.style.display = 'none';
                loader.count = 0;
                
                // Notifier le natif
                this.notifyNative('LOADER_HIDDEN', { id });
            }
        }

        /**
         * Intercepter les requêtes Fetch.
         */
        interceptFetch() {
            const originalFetch = window.fetch;
            
            window.fetch = async function(...args) {
                const [resource, config] = args;
                
                // Afficher le loader pour les requêtes longues
                const loaderId = `fetch-${Date.now()}`;
                const showLoaderTimeout = setTimeout(() => {
                    window.loaderManager.show(loaderId, 'Chargement des données...');
                }, 300); // Afficher après 300ms

                try {
                    const response = await originalFetch(...args);
                    clearTimeout(showLoaderTimeout);
                    window.loaderManager.hide(loaderId);
                    return response;
                } catch (error) {
                    clearTimeout(showLoaderTimeout);
                    window.loaderManager.hide(loaderId);
                    throw error;
                }
            };
        }

        /**
         * Intercepter les événements Livewire.
         */
        interceptLivewire() {
            if (window.Livewire) {
                // Afficher le loader au début d'une action
                window.Livewire.on('call', () => {
                    this.show(this.defaultLoaderId, 'Traitement en cours...');
                });

                // Masquer le loader à la fin
                window.Livewire.on('finished', () => {
                    this.hide(this.defaultLoaderId);
                });

                // Masquer le loader en cas d'erreur
                window.Livewire.on('error', () => {
                    this.hide(this.defaultLoaderId);
                });
            }
        }

        /**
         * Notifier le natif.
         */
        notifyNative(type, data = {}) {
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: type,
                    data: data,
                }));
            }
        }
    }

    // ──────────────────────────────────────────────
    // 2. GESTION DES ANIMATIONS
    // ──────────────────────────────────────────────

    class AnimationManager {
        constructor() {
            this.reducedMotion = this.prefersReducedMotion();
            this.init();
        }

        init() {
            // Appliquer les optimisations d'animation
            this.optimizeAnimations();
            
            // Écouter les changements de préférences
            this.watchMotionPreferences();
            
            console.log('[AnimationManager] Initialized');
        }

        /**
         * Vérifier si l'utilisateur préfère les animations réduites.
         */
        prefersReducedMotion() {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        /**
         * Optimiser les animations pour les WebViews.
         */
        optimizeAnimations() {
            const style = document.createElement('style');
            
            if (this.reducedMotion) {
                // Désactiver les animations si préférence utilisateur
                style.textContent = `
                    * {
                        animation-duration: 0.01ms !important;
                        animation-iteration-count: 1 !important;
                        transition-duration: 0.01ms !important;
                    }
                `;
            } else {
                // Optimiser les animations pour le natif
                style.textContent = `
                    /* Utiliser GPU pour les animations */
                    .fi-modal,
                    .fi-slide-over,
                    [role="dialog"] {
                        transform: translateZ(0);
                        will-change: transform;
                        animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
                    }

                    @keyframes slideIn {
                        from {
                            opacity: 0;
                            transform: translateY(20px) translateZ(0);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0) translateZ(0);
                        }
                    }

                    /* Transitions fluides */
                    input, textarea, select, button {
                        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                    }

                    /* Désactiver les animations de page entière */
                    html {
                        scroll-behavior: auto;
                    }

                    /* Optimiser les transitions de couleur */
                    .fi-btn {
                        transition: background-color 0.15s ease-out;
                    }
                `;
            }

            document.head.appendChild(style);
        }

        /**
         * Écouter les changements de préférences de mouvement.
         */
        watchMotionPreferences() {
            const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
            mediaQuery.addListener((e) => {
                this.reducedMotion = e.matches;
                this.optimizeAnimations();
            });
        }

        /**
         * Créer une transition fluide entre les pages.
         */
        createPageTransition() {
            const transition = document.createElement('div');
            transition.className = 'page-transition';
            transition.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: white;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease-out;
                z-index: 9999;
            `;
            document.body.appendChild(transition);
            return transition;
        }
    }

    // ──────────────────────────────────────────────
    // 3. GESTION DES TRANSITIONS DE PAGE
    // ──────────────────────────────────────────────

    class PageTransitionManager {
        constructor() {
            this.isTransitioning = false;
            this.init();
        }

        init() {
            // Intercepter les clics sur les liens
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a');
                if (link && this.shouldTransition(link)) {
                    e.preventDefault();
                    this.transitionTo(link.href);
                }
            });

            // Intercepter les soumissions de formulaires
            document.addEventListener('submit', (e) => {
                const form = e.target;
                if (this.shouldTransition(form)) {
                    // Laisser le formulaire se soumettre normalement
                    // mais ajouter une transition visuelle
                    this.startTransition();
                }
            });
        }

        /**
         * Vérifier si une transition est nécessaire.
         * IMPORTANT: ne jamais interférer avec Alpine.js / Livewire / Filament UI.
         */
        shouldTransition(element) {
            // Liens externes / nouveaux onglets → pas de transition
            if (element.target === '_blank' || element.target === '_external') {
                return false;
            }

            // Ancres (#) → pas de transition
            if (element.href && element.href.startsWith('#')) {
                return false;
            }

            // Liens javascript: ou href vide → Alpine.js triggers, ne pas intercepter
            if (!element.href || element.href.startsWith('javascript:')) {
                return false;
            }

            // Boutons → jamais (Filament action buttons, modal triggers)
            if (element.tagName === 'BUTTON') {
                return false;
            }

            // Composants Alpine.js (x-data) ou Livewire → ne pas intercepter
            if (element.closest('[x-data]') ||
                element.closest('[wire\\:click]') ||
                element.hasAttribute('wire:click') ||
                element.hasAttribute('x-on:click') ||
                element.hasAttribute('@click')) {
                return false;
            }

            // Éléments Filament UI (modals, sidebar, topbar, actions) → ne pas intercepter
            if (element.closest('.fi-modal') ||
                element.closest('.fi-slide-over') ||
                element.closest('.fi-topbar') ||
                element.closest('.fi-sidebar') ||
                element.closest('.fi-ta-row') ||
                element.closest('[role="dialog"]')) {
                return false;
            }

            // Uniquement les vrais liens de navigation avec URL absolue
            if (!element.href.startsWith('http')) {
                return false;
            }

            return true;
        }


        /**
         * Naviguer avec transition.
         */
        async transitionTo(url) {
            if (this.isTransitioning) return;

            this.isTransitioning = true;
            
            try {
                // Afficher la transition
                this.startTransition();

                // Attendre un peu pour l'effet visuel
                await new Promise(resolve => setTimeout(resolve, 150));

                // Naviguer
                window.location.href = url;
            } finally {
                this.isTransitioning = false;
            }
        }

        /**
         * Démarrer la transition.
         */
        startTransition() {
            window.loaderManager.show('page-transition', 'Chargement...');
        }
    }

    // ──────────────────────────────────────────────
    // 4. INITIALISATION
    // ──────────────────────────────────────────────

    window.loaderManager = new LoaderManager();
    window.animationManager = new AnimationManager();
    window.pageTransitionManager = new PageTransitionManager();

    console.log('[AnimationsLoader] Loaded');
})();
