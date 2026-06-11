{{-- Passkey login button — injected after the admin login form via render hook --}}
<div
    x-data="{
        loading: false,
        error: null,
        supported: typeof(PublicKeyCredential) !== 'undefined',

        // base64url helpers
        b64uToBuffer(b64u) {
            const b64 = b64u.replace(/-/g, '+').replace(/_/g, '/');
            const pad = b64.length % 4 ? '='.repeat(4 - b64.length % 4) : '';
            const bin = atob(b64 + pad);
            return Uint8Array.from(bin, c => c.charCodeAt(0)).buffer;
        },
        bufferToB64u(buf) {
            const bytes = new Uint8Array(buf);
            let bin = '';
            bytes.forEach(b => bin += String.fromCharCode(b));
            return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        },

        getCsrfToken() {
            const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
            return match ? decodeURIComponent(match[1]) : '';
        },

        async loginWithPasskey() {
            if (!this.supported) return;
            this.loading = true;
            this.error = null;
            try {
                const csrf = this.getCsrfToken();
                const baseHeaders = {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrf ? { 'X-XSRF-TOKEN': csrf } : {}),
                };

                // Step 1: get assertion options
                const optRes = await fetch('/webauthn/login/options', {
                    method: 'POST',
                    headers: baseHeaders,
                    credentials: 'same-origin',
                });
                if (!optRes.ok) {
                    this.error = 'Erreur serveur (' + optRes.status + ').';
                    return;
                }
                const options = await optRes.json();

                // Extract the one-time challenge token emitted by the server.
                // It is needed to identify the stored challenge on the verify request
                // because the session ID can change between the two HTTP requests.
                const challengeToken = options._wt || optRes.headers.get('X-WebAuthn-Token') || '';
                delete options._wt; // remove before passing to the WebAuthn browser API

                // Step 2: convert base64url to ArrayBuffer
                const publicKey = {
                    ...options,
                    challenge: this.b64uToBuffer(options.challenge),
                };
                if (options.allowCredentials) {
                    publicKey.allowCredentials = options.allowCredentials.map(c => ({
                        ...c,
                        id: this.b64uToBuffer(c.id),
                    }));
                }

                // Step 3: get credential via browser API
                const credential = await navigator.credentials.get({ publicKey });

                // Step 4: serialize response — include _wt so the backend can find the challenge
                const body = {
                    id: credential.id,
                    rawId: this.bufferToB64u(credential.rawId),
                    type: credential.type,
                    response: {
                        clientDataJSON: this.bufferToB64u(credential.response.clientDataJSON),
                        authenticatorData: this.bufferToB64u(credential.response.authenticatorData),
                        signature: this.bufferToB64u(credential.response.signature),
                        userHandle: credential.response.userHandle ? this.bufferToB64u(credential.response.userHandle) : null,
                    },
                    _wt: challengeToken,
                };

                // Step 5: send to server (re-read CSRF in case of refresh)
                const loginRes = await fetch('/webauthn/login', {
                    method: 'POST',
                    headers: {
                        ...baseHeaders,
                        'X-XSRF-TOKEN': this.getCsrfToken() || csrf,
                        ...(challengeToken ? { 'X-WebAuthn-Token': challengeToken } : {}),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                if (loginRes.ok) {
                    window.location.href = '{{ filament()->getUrl() }}';
                } else {
                    const errBody = await loginRes.text();
                    console.error('Login response:', loginRes.status, errBody);
                    this.error = 'Échec de l\'authentification (' + loginRes.status + ').';
                }
            } catch (e) {
                console.error('WebAuthn login error:', e);
                this.error = e.name === 'NotAllowedError'
                    ? 'Authentification annulée.'
                    : 'Erreur : ' + (e.message || 'Passkey non disponible.');
            } finally {
                this.loading = false;
            }
        }
    }"
    x-show="supported"
    x-cloak
    class="mt-4"
>
    <div class="relative flex items-center justify-center mb-4">
        <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
        </div>
        <span class="relative bg-white dark:bg-gray-900 px-3 text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">ou</span>
    </div>

    <button
        type="button"
        @click="loginWithPasskey()"
        :disabled="loading"
        {{-- Use the `primary-*` palette utilities so the brand colour stays
             wired to the Filament panel provider's primary palette and
             dark-mode counterparts come for free. Was hard-coded `#F6475F`. --}}
        class="group relative w-full overflow-hidden rounded-xl border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-5 py-3 text-sm font-semibold text-gray-700 dark:text-gray-200 shadow-sm transition-all duration-200 hover:border-primary-500/50 hover:shadow-md hover:shadow-primary-500/10 focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-wait"
    >
        <span class="absolute inset-0 bg-gradient-to-r from-primary-500/5 to-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
        <span class="relative inline-flex w-full items-center justify-center gap-3">
            {{-- Fingerprint icon --}}
            <span x-show="!loading" class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-500/10 transition-colors duration-200 group-hover:bg-primary-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-600 dark:text-primary-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4"/>
                    <path d="M14 13.12c0 2.38 0 6.38-1 8.88"/>
                    <path d="M17.29 21.02c.12-.6.43-2.3.5-3.02"/>
                    <path d="M2 12a10 10 0 0 1 18-6"/>
                    <path d="M2 16h.01"/>
                    <path d="M21.8 16c.2-2 .131-5.354 0-6"/>
                    <path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2"/>
                    <path d="M8.65 22c.21-.66.45-1.32.57-2"/>
                    <path d="M9 6.8a6 6 0 0 1 9 5.2v2"/>
                </svg>
            </span>
            {{-- Spinner --}}
            <svg x-show="loading" x-cloak class="animate-spin h-5 w-5 text-primary-600 dark:text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="flex flex-col items-start leading-tight">
                <span x-text="loading ? 'Vérification en cours...' : 'Se connecter avec une Passkey'" class="text-sm font-semibold"></span>
                <span x-show="!loading" class="text-[11px] font-normal text-gray-400 dark:text-gray-500">Empreinte, Face ID ou clé de sécurité</span>
            </span>
        </span>
    </button>

    <p x-show="error" x-text="error" class="mt-2 text-xs text-danger-600 dark:text-danger-400 text-center"></p>
</div>
