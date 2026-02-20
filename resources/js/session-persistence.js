/**
 * Session Persistence Manager
 * Gère la persistance des sessions et des tokens d'authentification en mode natif
 */
(function() {
    const isNative = window.ReactNativeWebView || window.location.search.includes('app_mode=native');
    
    if (!isNative) return;

    // ──────────────────────────────────────────────
    // 1. GESTION DES TOKENS
    // ──────────────────────────────────────────────

    class SessionManager {
        constructor() {
            this.storageKey = 'immoapp_session';
            this.tokenKey = 'immoapp_token';
            this.userKey = 'immoapp_user';
            this.expiryKey = 'immoapp_expiry';
            
            this.init();
        }

        /**
         * Initialiser le gestionnaire de session.
         */
        init() {
            // Restaurer la session au démarrage
            this.restoreSession();
            
            // Écouter les changements de session
            this.watchSessionChanges();
            
            // Configurer les intercepteurs d'authentification
            this.setupAuthInterceptors();
            
            console.log('[SessionManager] Initialized');
        }

        /**
         * Restaurer la session depuis le stockage local.
         */
        restoreSession() {
            try {
                const sessionData = localStorage.getItem(this.storageKey);
                const token = localStorage.getItem(this.tokenKey);
                const user = localStorage.getItem(this.userKey);
                const expiry = localStorage.getItem(this.expiryKey);

                if (token && user && expiry) {
                    // Vérifier si le token n'a pas expiré
                    if (parseInt(expiry) > Date.now()) {
                        // Ajouter le token aux en-têtes par défaut
                        this.setAuthHeader(token);
                        
                        // Notifier le natif que l'utilisateur est connecté
                        this.notifyNative('SESSION_RESTORED', {
                            user: JSON.parse(user),
                            token: token,
                        });
                        
                        console.log('[SessionManager] Session restored from storage');
                    } else {
                        // Token expiré, nettoyer
                        this.clearSession();
                        this.notifyNative('SESSION_EXPIRED');
                    }
                }
            } catch (error) {
                console.error('[SessionManager] Error restoring session:', error);
            }
        }

        /**
         * Sauvegarder la session dans le stockage local.
         */
        saveSession(token, user, expiryTime = null) {
            try {
                localStorage.setItem(this.tokenKey, token);
                localStorage.setItem(this.userKey, JSON.stringify(user));
                
                // Définir l'expiration (par défaut 30 jours)
                const expiry = expiryTime || (Date.now() + 30 * 24 * 60 * 60 * 1000);
                localStorage.setItem(this.expiryKey, expiry.toString());
                
                // Ajouter le token aux en-têtes
                this.setAuthHeader(token);
                
                // Notifier le natif
                this.notifyNative('SESSION_SAVED', {
                    user: user,
                    expiresIn: expiry - Date.now(),
                });
                
                console.log('[SessionManager] Session saved to storage');
            } catch (error) {
                console.error('[SessionManager] Error saving session:', error);
            }
        }

        /**
         * Effacer la session.
         */
        clearSession() {
            try {
                localStorage.removeItem(this.tokenKey);
                localStorage.removeItem(this.userKey);
                localStorage.removeItem(this.expiryKey);
                localStorage.removeItem(this.storageKey);
                
                // Supprimer le token des en-têtes
                this.removeAuthHeader();
                
                // Notifier le natif
                this.notifyNative('SESSION_CLEARED');
                
                console.log('[SessionManager] Session cleared');
            } catch (error) {
                console.error('[SessionManager] Error clearing session:', error);
            }
        }

        /**
         * Définir le token d'authentification dans les en-têtes.
         */
        setAuthHeader(token) {
            // Stocker le token pour les requêtes futures
            window.authToken = token;
            
            // Ajouter le token aux en-têtes Fetch
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                const [resource, config] = args;
                const headers = config?.headers || {};
                
                if (token && !headers['Authorization']) {
                    headers['Authorization'] = `Bearer ${token}`;
                }
                
                return originalFetch(resource, { ...config, headers });
            };
        }

        /**
         * Supprimer le token d'authentification.
         */
        removeAuthHeader() {
            window.authToken = null;
        }

        /**
         * Configurer les intercepteurs d'authentification.
         */
        setupAuthInterceptors() {
            // Intercepter les réponses 401 (non autorisé)
            const originalFetch = window.fetch;
            window.fetch = async function(...args) {
                const response = await originalFetch(...args);
                
                if (response.status === 401) {
                    // Token expiré ou invalide
                    window.sessionManager.clearSession();
                    window.sessionManager.notifyNative('AUTH_FAILED', {
                        status: 401,
                        message: 'Unauthorized',
                    });
                    
                    // Rediriger vers la page de connexion
                    window.location.href = '/owner/login?redirect=' + encodeURIComponent(window.location.href);
                }
                
                return response;
            };
        }

        /**
         * Écouter les changements de session.
         */
        watchSessionChanges() {
            // Écouter les événements de stockage (changements dans d'autres onglets)
            window.addEventListener('storage', (event) => {
                if (event.key === this.tokenKey && !event.newValue) {
                    // Token supprimé dans un autre onglet
                    this.clearSession();
                }
            });

            // Écouter les événements de déconnexion
            document.addEventListener('logout', () => {
                this.clearSession();
            });
        }

        /**
         * Notifier l'application native.
         */
        notifyNative(type, data = {}) {
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: type,
                    data: data,
                    timestamp: new Date().toISOString(),
                }));
            }
        }

        /**
         * Vérifier si l'utilisateur est connecté.
         */
        isAuthenticated() {
            const token = localStorage.getItem(this.tokenKey);
            const expiry = localStorage.getItem(this.expiryKey);
            
            return token && expiry && parseInt(expiry) > Date.now();
        }

        /**
         * Obtenir l'utilisateur actuel.
         */
        getCurrentUser() {
            try {
                const user = localStorage.getItem(this.userKey);
                return user ? JSON.parse(user) : null;
            } catch (error) {
                console.error('[SessionManager] Error getting current user:', error);
                return null;
            }
        }

        /**
         * Renouveler le token.
         */
        async refreshToken() {
            try {
                const response = await fetch('/api/auth/refresh', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem(this.tokenKey)}`,
                    },
                });

                if (response.ok) {
                    const data = await response.json();
                    this.saveSession(data.token, data.user, data.expires_at);
                    return true;
                } else {
                    this.clearSession();
                    return false;
                }
            } catch (error) {
                console.error('[SessionManager] Error refreshing token:', error);
                return false;
            }
        }
    }

    // Initialiser le gestionnaire de session global
    window.sessionManager = new SessionManager();

    // ──────────────────────────────────────────────
    // 2. INTERCEPTEURS DE FORMULAIRES
    // ──────────────────────────────────────────────

    // Intercepter les soumissions de formulaires de connexion
    document.addEventListener('submit', (event) => {
        const form = event.target;
        
        // Détecter les formulaires de connexion
        if (form.action.includes('/login') || form.action.includes('/auth')) {
            form.addEventListener('submit', async (e) => {
                // Attendre la réponse du serveur
                setTimeout(() => {
                    // Vérifier si la connexion a réussi
                    if (window.sessionManager.isAuthenticated()) {
                        console.log('[SessionManager] Login successful');
                    }
                }, 500);
            });
        }
    });

    // ──────────────────────────────────────────────
    // 3. RENOUVELLEMENT AUTOMATIQUE DU TOKEN
    // ──────────────────────────────────────────────

    // Renouveler le token 5 minutes avant son expiration
    setInterval(() => {
        const expiry = localStorage.getItem('immoapp_expiry');
        if (expiry) {
            const timeUntilExpiry = parseInt(expiry) - Date.now();
            
            // Si moins de 5 minutes avant expiration
            if (timeUntilExpiry > 0 && timeUntilExpiry < 5 * 60 * 1000) {
                window.sessionManager.refreshToken();
            }
        }
    }, 60000); // Vérifier toutes les minutes

    console.log('[SessionPersistence] Loaded');
})();
