<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Allowed origins: React Native WebView sends 'null', production sends our domain
        const ALLOWED_ORIGINS = ['null', '{{ config("app.url") }}'];

        window.addEventListener('message', (event) => {
            try {
                // Validate origin — only accept messages from trusted sources
                if (!ALLOWED_ORIGINS.includes(String(event.origin))) return;

                // Ignore empty messages
                if (!event.data) return;

                const message = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;

                if (message && message.type === 'LOCATION_RECEIVED') {
                    const { latitude, longitude } = message.data || {};

                    if (typeof latitude !== 'number' || typeof longitude !== 'number') return;
                    if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) return;

                    // On cherche spécifiquement le composant MapPicker de Dotswan
                    const mapPicker = document.querySelector('[data-map-picker]');
                    if (mapPicker) {
                        // On envoie un événement personnalisé que le composant MapPicker peut écouter
                        mapPicker.dispatchEvent(new CustomEvent('location-updated', {
                            detail: { lat: latitude, lng: longitude }
                        }));
                    }

                    // Fallback pour les formulaires Livewire classiques
                    const wireEl = document.querySelector('[wire\\:id]');
                    if (wireEl) {
                        const wireId = wireEl.getAttribute('wire:id');
                        const adForm = Livewire.find(wireId);
                        if (adForm) {
                            // On met à jour les champs de localisation si présents
                            if (adForm.get('data.location_map')) {
                                adForm.set('data.location_map.lat', latitude);
                                adForm.set('data.location_map.lng', longitude);
                            } else {
                                adForm.set('data.latitude', latitude);
                                adForm.set('data.longitude', longitude);
                            }

                            if (window.FilamentNotification) {
                                new FilamentNotification()
                                    .title('Position GPS reçue')
                                    .body(`Lat: ${latitude.toFixed(6)}, Lng: ${longitude.toFixed(6)}`)
                                    .success()
                                    .send();
                            }
                        }
                    }
                }
            } catch (e) {
                // Silent catch for non-JSON messages or unrelated errors
            }
        });
    });
</script>
