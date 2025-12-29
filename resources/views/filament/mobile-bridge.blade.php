<script>
    document.addEventListener('DOMContentLoaded', () => {
        console.log("[MobileBridge] Initialized");

        window.addEventListener('message', (event) => {
            try {
                // Ignore empty messages
                if (!event.data) return;

                const message = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;

                console.log("[MobileBridge] Message received:", message.type);

                if (message && message.type === 'LOCATION_RECEIVED') {
                    const { latitude, longitude } = message.data;

                    // Broadcast user location to all Livewire components that might have these fields
                    const components = Livewire.all();
                    let updated = false;

                    components.forEach(component => {
                        try {
                            // Try setting the data on the component
                            // We assume Filament uses 'data.*' for form state
                            component.set('data.latitude', latitude);
                            component.set('data.longitude', longitude);
                            updated = true;
                        } catch (err) {
                            // Component might not have these properties
                        }
                    });

                    if (updated && window.FilamentNotification) {
                        new FilamentNotification()
                            .title('Position GPS reçue')
                            .body(`Lat: ${latitude}, Lng: ${longitude}`)
                            .success()
                            .send();
                    }
                }
            } catch (e) {
                // Silent catch for non-JSON messages or unrelated errors
            }
        });
    });
</script>
