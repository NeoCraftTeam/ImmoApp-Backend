{{-- Passkey management component for admin profile --}}
<div
    x-data="{
        passkeys: @js($passkeys),
        registering: false,
        showNameInput: false,
        passkeyName: '',
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

        async registerPasskey() {
            this.registering = true;
            this.error = null;
            try {
                // Step 1: get creation options from server
                const optRes = await fetch('/webauthn/register/options', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!optRes.ok) {
                    const err = await optRes.text();
                    console.error('Options response:', optRes.status, err);
                    this.error = 'Erreur serveur lors de la création des options (' + optRes.status + ').';
                    return;
                }
                const options = await optRes.json();
                console.log('WebAuthn options received:', options);

                // Step 2: convert base64url fields to ArrayBuffers for the browser API
                const publicKey = {
                    ...options,
                    challenge: this.b64uToBuffer(options.challenge),
                    user: {
                        ...options.user,
                        id: this.b64uToBuffer(options.user.id),
                    },
                };
                if (options.excludeCredentials) {
                    publicKey.excludeCredentials = options.excludeCredentials.map(c => ({
                        ...c,
                        id: this.b64uToBuffer(c.id),
                    }));
                }

                // Step 3: create credential via browser WebAuthn API
                const credential = await navigator.credentials.create({ publicKey });
                console.log('Credential created:', credential);

                // Step 4: serialize the credential response back to base64url
                const body = {
                    id: credential.id,
                    rawId: this.bufferToB64u(credential.rawId),
                    type: credential.type,
                    response: {
                        clientDataJSON: this.bufferToB64u(credential.response.clientDataJSON),
                        attestationObject: this.bufferToB64u(credential.response.attestationObject),
                    },
                };
                if (credential.response.getTransports) {
                    body.response.transports = credential.response.getTransports();
                }
                if (this.passkeyName.trim()) {
                    body.alias = this.passkeyName.trim();
                }

                console.log('Sending attestation:', body);

                // Step 5: send to server for verification and storage
                const regRes = await fetch('/webauthn/register', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                if (regRes.ok || regRes.status === 204) {
                    window.location.reload();
                } else {
                    const errBody = await regRes.text();
                    console.error('Register response:', regRes.status, errBody);
                    this.error = 'Erreur lors de l\'enregistrement (' + regRes.status + ').';
                }
            } catch (e) {
                console.error('WebAuthn registration error:', e);
                this.error = e.name === 'NotAllowedError'
                    ? 'Enregistrement annulé par l\'utilisateur.'
                    : 'Erreur : ' + (e.message || 'Enregistrement non supporté.');
            } finally {
                this.registering = false;
            }
        },
        async deletePasskey(id) {
            if (!confirm('Supprimer cette passkey ?')) return;
            try {
                const res = await fetch('/webauthn/credentials/' + id, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                });
                if (res.ok) {
                    this.passkeys = this.passkeys.filter(p => p.id !== id);
                }
            } catch (e) {
                alert('Erreur lors de la suppression.');
            }
        }
    }"
>
    @if($passkeys && count($passkeys) > 0)
        <div class="space-y-2">
            @foreach($passkeys as $pk)
                <div class="flex items-center justify-between rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                            <x-heroicon-s-finger-print class="w-5 h-5 text-primary-500" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                {{ $pk['alias'] ?? 'Passkey' }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                Enregistrée le {{ $pk['created_at'] }}
                                @if($pk['last_used'])
                                    — Dernière utilisation : {{ $pk['last_used'] }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="deletePasskey('{{ $pk['id'] }}')"
                        class="text-xs text-danger-600 dark:text-danger-400 hover:underline font-medium"
                    >
                        Supprimer
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400" x-show="supported">
            Aucune passkey enregistrée. Ajoutez-en une pour vous connecter sans mot de passe.
        </p>
        <p class="text-sm text-amber-600 dark:text-amber-400" x-show="!supported">
            Votre navigateur ne supporte pas les passkeys.
        </p>
    @endif

    <div class="mt-3" x-show="supported">
        {{-- Step 1: show name input --}}
        <div x-show="!showNameInput && !registering">
            <button
                type="button"
                @click="showNameInput = true; $nextTick(() => $refs.passkeyNameInput?.focus())"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1"
            >
                <x-heroicon-s-plus class="w-4 h-4" />
                Ajouter une passkey
            </button>
        </div>

        {{-- Step 2: name input + confirm --}}
        <div x-show="showNameInput || registering" x-cloak class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Nom de la passkey
            </label>
            <div class="flex items-center gap-2">
                <input
                    x-ref="passkeyNameInput"
                    x-model="passkeyName"
                    type="text"
                    placeholder="ex : MacBook Pro, iPhone 15..."
                    :disabled="registering"
                    @keydown.enter="registerPasskey()"
                    class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 disabled:opacity-50"
                />
                <button
                    type="button"
                    @click="registerPasskey()"
                    :disabled="registering || !passkeyName.trim()"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-wait"
                >
                    <x-heroicon-s-finger-print class="w-4 h-4" />
                    <span x-text="registering ? 'Enregistrement...' : 'Enregistrer'"></span>
                </button>
                <button
                    type="button"
                    x-show="!registering"
                    @click="showNameInput = false; passkeyName = ''; error = null"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition"
                >
                    Annuler
                </button>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500">
                Donnez un nom reconnaissable pour identifier cet appareil.
            </p>
        </div>
    </div>

    <p x-show="error" x-text="error" class="mt-2 text-xs text-danger-600 dark:text-danger-400"></p>
</div>
