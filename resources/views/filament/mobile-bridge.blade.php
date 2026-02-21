<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ALLOWED_ORIGINS = ['null', '{{ config("app.url") }}'];

        /**
         * Fix #6 — Validation origin via window.isNativeApp (plus sûr que 'null' brut)
         */
        function isFromNativeApp(event) {
            if (window.isNativeApp === true) return true;
            return ALLOWED_ORIGINS.includes(String(event.origin));
        }

        /**
         * Fix #5 — sendWithRetry ACK : accusé réception vers le natif
         */
        function sendAck(ackKey) {
            if (window.ReactNativeWebView && ackKey) {
                window.ReactNativeWebView.postMessage(
                    JSON.stringify({ type: 'ACK_RECEIVED', data: { _ackKey: ackKey } })
                );
            }
        }

        window.addEventListener('message', (event) => {
            try {
                // Fix #6 — validation origin améliorée
                if (!isFromNativeApp(event)) return;
                if (!event.data) return;

                const message = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                if (!message?.type) return;

                switch (message.type) {
                    case 'LOCATION_RECEIVED':
                        handleLocationReceived(message.data);
                        sendAck(message.data?._ackKey); // Fix #5
                        break;

                    // Fix #16 — Gérer les images sélectionnées depuis la galerie/caméra native
                    case 'IMAGE_SELECTED':
                    case 'PHOTO_TAKEN':
                        handleImageReceived(message.data);
                        sendAck(message.data?._ackKey); // Fix #5
                        break;

                    default:
                        break;
                }
            } catch (e) {
                // Silent catch pour les messages non-JSON ou non liés
            }
        });

        /**
         * LOCATION_RECEIVED — injecter les coordonnées GPS dans le formulaire Filament.
         */
        function handleLocationReceived(data) {
            const { latitude, longitude } = data || {};
            if (typeof latitude !== 'number' || typeof longitude !== 'number') return;
            if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) return;

            // MapPicker Dotswan
            const mapPicker = document.querySelector('[data-map-picker]');
            if (mapPicker) {
                mapPicker.dispatchEvent(new CustomEvent('location-updated', {
                    detail: { lat: latitude, lng: longitude }
                }));
            }

            // Fallback Livewire
            const wireEl = document.querySelector('[wire\\:id]');
            if (wireEl) {
                const wireId = wireEl.getAttribute('wire:id');
                const adForm = Livewire.find(wireId);
                if (adForm) {
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

        /**
         * Fix #16 — IMAGE_SELECTED / PHOTO_TAKEN
         * Injecter l'image native dans le FileUpload Filament actif.
         * On crée un File blob depuis l'URI (data URL) et on le dispatche
         * sur le composant Livewire FileUpload en cours d'utilisation.
         */
        async function handleImageReceived(data) {
            const { uri, mimeType = 'image/jpeg', fileName = 'photo.jpg' } = data || {};
            if (!uri) return;

            try {
                // Convertir l'URI (data URL ou blob URL) en File
                const response = await fetch(uri);
                const blob = await response.blob();
                const file = new File([blob], fileName, { type: mimeType });

                // Trouver le FileUpload Filament actif (celui qui a demandé la photo)
                const fileInput = document.querySelector(
                    'input[type="file"][data-native-upload], ' +
                    '.fi-fo-file-upload input[type="file"]'
                );

                if (fileInput) {
                    // Créer un DataTransfer pour simuler la sélection native
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    fileInput.files = dataTransfer.files;
                    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                } else if (window.FilamentNotification) {
                    // Aucun FileUpload trouvé — notifier l'utilisateur
                    new FilamentNotification()
                        .title('Photo reçue')
                        .body('Veuillez cliquer sur le bouton d\'upload pour attacher la photo.')
                        .info()
                        .send();
                }
            } catch (err) {
                console.warn('[mobile-bridge] Erreur image native:', err);
            }
        }
    });
</script>
