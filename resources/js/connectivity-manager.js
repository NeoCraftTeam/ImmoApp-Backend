/**
 * Connectivity Manager
 * Gère l'état de connectivité et le mode hors-ligne pour les WebViews React Native
 */
(function() {
    const isNative = window.ReactNativeWebView || window.location.search.includes('app_mode=native');
    
    if (!isNative) return;

    // ──────────────────────────────────────────────
    // 1. GESTION DE LA CONNECTIVITÉ
    // ──────────────────────────────────────────────

    class ConnectivityManager {
        constructor() {
            this.isOnline = navigator.onLine;
            this.connectionType = this.getConnectionType();
            this.listeners = [];
            this.requestQueue = [];
            this.isProcessingQueue = false;
            
            this.init();
        }

        init() {
            // Écouter les changements de connectivité
            window.addEventListener('online', () => this.handleOnline());
            window.addEventListener('offline', () => this.handleOffline());

            // Écouter les changements de type de connexion (si disponible)
            if (navigator.connection) {
                navigator.connection.addEventListener('change', () => {
                    this.connectionType = this.getConnectionType();
                    this.notifyListeners('CONNECTION_TYPE_CHANGED', {
                        type: this.connectionType,
                    });
                });
            }

            // Intercepter les requêtes Fetch
            this.interceptFetch();

            // Vérifier la connectivité périodiquement
            this.startConnectivityCheck();

            console.log('[ConnectivityManager] Initialized');
        }

        /**
         * Obtenir le type de connexion.
         */
        getConnectionType() {
            if (!navigator.connection) {
                return this.isOnline ? 'unknown' : 'none';
            }

            const connection = navigator.connection;
            const type = connection.type || connection.effectiveType;

            return type;
        }

        /**
         * Gérer la reconnexion.
         */
        handleOnline() {
            this.isOnline = true;
            this.connectionType = this.getConnectionType();

            console.log('[ConnectivityManager] Online - Type:', this.connectionType);

            // Notifier le natif
            this.notifyNative('ONLINE', {
                connectionType: this.connectionType,
            });

            // Notifier les listeners
            this.notifyListeners('ONLINE', {
                connectionType: this.connectionType,
            });

            // Masquer l'indicateur hors-ligne
            this.hideOfflineIndicator();

            // Traiter la queue de requêtes
            this.processRequestQueue();
        }

        /**
         * Gérer la déconnexion.
         */
        handleOffline() {
            this.isOnline = false;

            console.log('[ConnectivityManager] Offline');

            // Notifier le natif
            this.notifyNative('OFFLINE');

            // Notifier les listeners
            this.notifyListeners('OFFLINE');

            // Afficher l'indicateur hors-ligne
            this.showOfflineIndicator();
        }

        /**
         * Vérifier la connectivité périodiquement.
         */
        startConnectivityCheck() {
            setInterval(async () => {
                try {
                    const response = await fetch('/api/health', {
                        method: 'HEAD',
                        cache: 'no-cache',
                    });

                    if (response.ok && !this.isOnline) {
                        // Reconnecté
                        this.handleOnline();
                    }
                } catch (error) {
                    if (this.isOnline) {
                        // Déconnecté
                        this.handleOffline();
                    }
                }
            }, 30000); // Vérifier toutes les 30 secondes
        }

        /**
         * Intercepter les requêtes Fetch.
         */
        interceptFetch() {
            const originalFetch = window.fetch;

            window.fetch = async (...args) => {
                const [resource, config] = args;

                // Si hors-ligne, mettre en queue
                if (!this.isOnline && this.isWriteRequest(resource, config)) {
                    return this.queueRequest(resource, config);
                }

                try {
                    const response = await originalFetch(...args);

                    // Si 5xx et hors-ligne, mettre en queue
                    if (response.status >= 500 && !this.isOnline) {
                        return this.queueRequest(resource, config);
                    }

                    return response;
                } catch (error) {
                    // Erreur réseau
                    if (!this.isOnline) {
                        return this.queueRequest(resource, config);
                    }

                    throw error;
                }
            };
        }

        /**
         * Vérifier si c'est une requête d'écriture.
         */
        isWriteRequest(resource, config) {
            const method = (config?.method || 'GET').toUpperCase();
            return ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
        }

        /**
         * Mettre en queue une requête.
         */
        queueRequest(resource, config) {
            return new Promise((resolve, reject) => {
                this.requestQueue.push({
                    resource,
                    config,
                    resolve,
                    reject,
                });

                console.log('[ConnectivityManager] Request queued. Queue size:', this.requestQueue.length);

                // Notifier le natif
                this.notifyNative('REQUEST_QUEUED', {
                    queueSize: this.requestQueue.length,
                });
            });
        }

        /**
         * Traiter la queue de requêtes.
         */
        async processRequestQueue() {
            if (this.isProcessingQueue || this.requestQueue.length === 0) {
                return;
            }

            this.isProcessingQueue = true;

            while (this.requestQueue.length > 0) {
                const { resource, config, resolve, reject } = this.requestQueue.shift();

                try {
                    const response = await fetch(resource, config);
                    resolve(response);

                    console.log('[ConnectivityManager] Queued request processed successfully');
                } catch (error) {
                    reject(error);

                    console.error('[ConnectivityManager] Queued request failed:', error);

                    // Remettre en queue si erreur
                    this.requestQueue.unshift({ resource, config, resolve, reject });
                    break;
                }
            }

            this.isProcessingQueue = false;

            // Notifier le natif
            if (this.requestQueue.length === 0) {
                this.notifyNative('REQUEST_QUEUE_PROCESSED');
            }
        }

        /**
         * Afficher l'indicateur hors-ligne.
         */
        showOfflineIndicator() {
            let indicator = document.getElementById('offline-indicator');

            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'offline-indicator';
                indicator.className = 'offline-indicator';
                indicator.innerHTML = `
                    <div class="offline-content">
                        <svg class="offline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071l3.528-3.528m3.104-3.104l3.528-3.528m-9.16 9.16L5.939 5.939m9.16 9.16l3.528-3.528" />
                        </svg>
                        <span class="offline-text">Mode hors-ligne</span>
                    </div>
                `;
                document.body.appendChild(indicator);
            }

            indicator.style.display = 'block';
        }

        /**
         * Masquer l'indicateur hors-ligne.
         */
        hideOfflineIndicator() {
            const indicator = document.getElementById('offline-indicator');
            if (indicator) {
                indicator.style.display = 'none';
            }
        }

        /**
         * S'abonner aux changements de connectivité.
         */
        subscribe(listener) {
            this.listeners.push(listener);
            return () => {
                this.listeners = this.listeners.filter(l => l !== listener);
            };
        }

        /**
         * Notifier les listeners.
         */
        notifyListeners(type, data = {}) {
            this.listeners.forEach(listener => {
                try {
                    listener({ type, data });
                } catch (error) {
                    console.error('[ConnectivityManager] Listener error:', error);
                }
            });
        }

        /**
         * Notifier le natif.
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
         * Obtenir le statut actuel.
         */
        getStatus() {
            return {
                isOnline: this.isOnline,
                connectionType: this.connectionType,
                queueSize: this.requestQueue.length,
            };
        }
    }

    // ──────────────────────────────────────────────
    // 2. INITIALISATION
    // ──────────────────────────────────────────────

    window.connectivityManager = new ConnectivityManager();

    // Exposer une API publique
    window.getConnectivityStatus = () => window.connectivityManager.getStatus();
    window.onConnectivityChange = (listener) => window.connectivityManager.subscribe(listener);

    console.log('[ConnectivityManager] Loaded');
})();
