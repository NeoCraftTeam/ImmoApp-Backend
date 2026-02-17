// Mobile app detection script for Filament panels
// Loaded as external JS asset via Filament's ->assets() method
(function() {
    if (window.location.search.includes('app_mode=native') || window.ReactNativeWebView) {
        document.body.classList.add('is-mobile-app');
    }
})();
