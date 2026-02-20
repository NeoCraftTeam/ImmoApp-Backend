<script>
    /**
     * Native App Initialization
     * Initialise tous les systèmes de gestion pour les WebViews React Native
     */
    (function() {
        const isNative = window.ReactNativeWebView || window.location.search.includes('app_mode=native');
        
        if (!isNative) return;

        // Ajouter la classe native au body
        document.documentElement.classList.add('is-native-app');

        // Configuration initiale
        const config = {
            app: {
                name: '{{ config("app.name") }}',
                url: '{{ config("app.url") }}',
                env: '{{ config("app.env") }}',
            },
            session: {
                cookieName: '{{ config("session.cookie") }}',
                lifetime: {{ config("session.lifetime") }},
            },
        };

        window.nativeAppConfig = config;

        // Notifier le natif que l'app est prête
        function notifyNativeReady() {
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: 'APP_READY',
                    data: {
                        url: window.location.href,
                        isAuthenticated: !!window.sessionManager?.isAuthenticated(),
                        user: window.sessionManager?.getCurrentUser(),
                    },
                }));
            }
        }

        // Attendre que tous les managers soient initialisés
        const checkReady = setInterval(() => {
            if (window.sessionManager && 
                window.loaderManager && 
                window.animationManager && 
                window.connectivityManager) {
                clearInterval(checkReady);
                notifyNativeReady();
            }
        }, 100);

        // Timeout de sécurité
        setTimeout(() => {
            clearInterval(checkReady);
            notifyNativeReady();
        }, 5000);

        console.log('[NativeInit] Initialization started');
    })();
</script>
