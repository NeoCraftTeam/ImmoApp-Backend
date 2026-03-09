{{-- resources/views/filament/components/tour-hotspot-editor.blade.php --}}
{{-- Shown only when has_3d_tour = true --}}

@if (!$getRecord()?->tour_config || empty($getRecord()?->tour_config['scenes']))
    <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 px-4 py-5 flex items-start gap-3">
        <span class="text-amber-500 text-2xl mt-0.5">💾</span>
        <div>
            <p class="font-semibold text-amber-800 dark:text-amber-300 text-sm">Sauvegarde requise pour éditer les liens</p>
            <p class="text-amber-700 dark:text-amber-400 text-sm mt-1">
                Après avoir ajouté ou modifié vos pièces dans l'<strong>Étape 1</strong>, vous devez <strong>sauvegarder l'annonce</strong> (bouton en bas de page) pour que les photos soient traitées et prêtes à être liées ici.
            </p>
        </div>
    </div>
@else

<script>
(function () {
    const PANNELLUM_JS  = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js';
    const PANNELLUM_CSS = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css';

    function ensurePannellumCSS() {
        if (!document.querySelector('link[href="' + PANNELLUM_CSS + '"]')) {
            var link = document.createElement('link');
            link.rel  = 'stylesheet';
            link.href = PANNELLUM_CSS;
            document.head.appendChild(link);
        }
    }

    function loadPannellum() {
        ensurePannellumCSS();
        if (window.pannellum) { return Promise.resolve(); }
        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[src="' + PANNELLUM_JS + '"]');
            var tag = existing || document.createElement('script');
            if (!existing) {
                tag.src = PANNELLUM_JS;
                document.head.appendChild(tag);
            }
            if (window.pannellum) { resolve(); return; }
            tag.addEventListener('load',  resolve, { once: true });
            tag.addEventListener('error', reject,  { once: true });
        });
    }

    function getScenes(config) {
        if (!config || !config.scenes) { return []; }
        return Array.isArray(config.scenes) ? config.scenes : Object.values(config.scenes);
    }

    function makeHotspotEditor(config, propertyId) {
        /* Normalise scenes to a sequential array */
        if (config && config.scenes && !Array.isArray(config.scenes)) {
            config.scenes = Object.values(config.scenes);
        }

        var scenes        = getScenes(config);
        var activeSceneId = scenes.length > 0 ? scenes[0].id : null;

        return {
            config:        config,
            propertyId:    propertyId,
            scenes:        scenes,
            activeSceneId: activeSceneId,
            activeScene:   scenes.length > 0 ? scenes[0] : null,
            viewer:        null,
            _mouseHandler: null,
            isPlacing:     false,
            showDialog:    false,
            isSaving:      false,
            saveSuccess:   false,
            newHotspot:    { target_scene: '', label: '', pitch: 0, yaw: 0 },

            init: function () {
                var self = this;
                loadPannellum()
                    .then(function () {
                        if (self.activeScene) { self.loadScene(self.activeScene); }
                    })
                    .catch(function (err) {
                        console.error('[HotspotEditor] Pannellum failed to load:', err);
                    });
            },

            setActiveScene: function (scene) {
                this.activeSceneId = scene.id;
                this.activeScene   = scene;
            },

            destroyViewer: function () {
                if (!this.viewer) { return; }
                try {
                    if (this._mouseHandler) {
                        var c = this.viewer.getContainer ? this.viewer.getContainer() : null;
                        if (c) { c.removeEventListener('mousedown', this._mouseHandler); }
                    }
                    this.viewer.destroy();
                } catch (_) {}
                this.viewer        = null;
                this._mouseHandler = null;
            },

            loadScene: function (scene) {
                if (!scene) { return; }
                this.setActiveScene(scene);
                this.destroyViewer();
                var self = this;
                this.$nextTick(function () {
                    if (!window.pannellum) { return; }
                    var container = document.getElementById('hotspot-editor-viewer');
                    if (!container) { return; }

                    self.viewer = window.pannellum.viewer(container, {
                        type:         'equirectangular',
                        panorama:     scene.image_url,
                        autoLoad:     true,
                        hfov:         110,
                        showControls: true,
                        hotSpots:     (scene.hotspots || []).map(function (hs, i) {
                            return {
                                pitch:    hs.pitch,
                                yaw:      hs.yaw,
                                type:     'custom',
                                text:     hs.label,
                                cssClass: 'kh-hotspot',
                                id:       'hs_' + i,
                            };
                        }),
                    });

                    /*
                     * Pannellum's viewer.on() only supports 'load', 'error', 'scenechange'.
                     * Click capture requires a DOM mousedown listener on the container;
                     * viewer.mouseEventToCoords(event) → [pitch, yaw].
                     */
                    self._mouseHandler = function (event) {
                        if (!self.isPlacing || !self.viewer) { return; }
                        var coords = self.viewer.mouseEventToCoords(event);
                        if (!Array.isArray(coords)) { return; }
                        var other = self.scenes.find(function (s) { return s.id !== self.activeSceneId; });
                        self.newHotspot = { target_scene: other ? other.id : '', label: '', pitch: coords[0], yaw: coords[1] };
                        self.showDialog = true;
                    };
                    self.viewer.getContainer().addEventListener('mousedown', self._mouseHandler);
                });
            },

            startPlacing: function () {
                this.isPlacing  = !this.isPlacing;
                this.showDialog = false;
            },

            confirmHotspot: function () {
                if (!this.activeScene) { return; }
                if (!Array.isArray(this.activeScene.hotspots)) { this.activeScene.hotspots = []; }
                this.activeScene.hotspots.push(Object.assign({}, this.newHotspot));
                this.showDialog = false;
                this.isPlacing  = false;
                this.loadScene(this.activeScene);
            },

            removeHotspot: function (index) {
                if (!this.activeScene || !this.activeScene.hotspots) { return; }
                this.activeScene.hotspots.splice(index, 1);
                this.loadScene(this.activeScene);
            },

            getSceneTitle: function (sceneId) {
                var found = this.scenes.find(function (s) { return s.id === sceneId; });
                return found ? found.title : sceneId;
            },

            saveHotspots: function () {
                var self = this;
                if (!self.activeScene) { return; }
                self.isSaving    = true;
                self.saveSuccess = false;
                var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                fetch('/panel-api/v1/ads/' + self.propertyId + '/tour/scenes/' + self.activeSceneId + '/hotspots', {
                    method:      'PATCH',
                    headers:     { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    credentials: 'same-origin',
                    body:        JSON.stringify({ hotspots: self.activeScene.hotspots || [] }),
                })
                .then(function (res) {
                    if (!res.ok) { throw new Error('HTTP ' + res.status); }
                    self.saveSuccess = true;
                    setTimeout(function () { self.saveSuccess = false; }, 3000);
                })
                .catch(function (e) { alert('Erreur lors de la sauvegarde : ' + e.message); })
                .finally(function () { self.isSaving = false; });
            },
        };
    }

    /*
     * Register with Alpine.data() — the canonical way for named Alpine components.
     *
     * alpine:init fires synchronously before Alpine processes any x-data elements.
     * On initial page load the inline script runs before Alpine (which is deferred),
     * so the listener will fire at the right time.
     * On Livewire re-renders Alpine is already running: register immediately.
     */
    function register() {
        if (window.Alpine && window.Alpine.data) {
            window.Alpine.data('hotspotEditor', makeHotspotEditor);
        }
    }

    document.addEventListener('alpine:init', register);
    register(); /* also try immediately in case Alpine already booted */
}());
</script>

<style>
.kh-hotspot {
    width: 32px !important;
    height: 32px !important;
    background: rgba(255, 255, 255, 0.9) !important;
    border-radius: 50% !important;
    border: 3px solid #3b82f6 !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
    transition: transform 0.2s !important;
}
.kh-hotspot:hover { transform: scale(1.2) !important; }
.kh-hotspot::after { content: "▶" !important; color: #3b82f6 !important; font-size: 10px !important; }
</style>

<div x-data="hotspotEditor(@js($getRecord()?->tour_config), @js($getRecord()?->id))" class="space-y-4">

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Sélectionnez une pièce, cliquez sur <strong>Ajouter un lien</strong>, puis cliquez
        sur l'endroit de la photo d'où vous voulez naviguer vers une autre pièce.
    </p>

    {{-- Scene selector tabs --}}
    <div class="flex gap-2 flex-wrap">
        <template x-for="scene in scenes" :key="scene.id">
            <button
                @click="activeSceneId = scene.id; loadScene(scene)"
                :class="activeSceneId === scene.id
                    ? 'bg-primary-600 text-white border-primary-600'
                    : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-600 hover:border-primary-400'"
                class="px-4 py-2 rounded-lg text-sm font-medium border transition-all flex items-center gap-2"
            >
                <span x-text="scene.title"></span>
                <span
                    x-show="scene.hotspots?.length"
                    class="bg-green-500 text-white text-xs rounded-full px-1.5 py-0.5"
                    x-text="scene.hotspots?.length"
                ></span>
            </button>
        </template>
    </div>

    {{-- 360° Viewer --}}
    <div class="relative rounded-xl overflow-hidden bg-gray-900" style="height: 450px;">
        <div id="hotspot-editor-viewer" class="w-full h-full"></div>

        {{-- Placement mode overlay --}}
        <template x-if="isPlacing">
            <div class="absolute inset-0 pointer-events-none z-10 border-4 border-orange-400 rounded-xl flex items-start justify-center pt-4">
                <div class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm font-medium animate-pulse">
                    🎯 Cliquez sur la vue pour placer le lien
                </div>
            </div>
        </template>

        {{-- Action button --}}
        <div class="absolute top-3 right-3 flex gap-2 z-20">
            <button
                @click="startPlacing()"
                :class="isPlacing ? 'bg-orange-500 text-white' : 'bg-white text-gray-800 hover:bg-gray-100'"
                class="px-3 py-2 rounded-lg text-sm font-medium shadow transition-all"
                x-text="isPlacing ? '❌ Annuler' : '➕ Ajouter un lien'"
            ></button>
        </div>

        {{-- Hotspot list overlay --}}
        <div class="absolute bottom-3 left-3 z-20 space-y-1 max-w-xs">
            <template x-for="(hs, i) in activeScene?.hotspots ?? []" :key="i">
                <div class="flex items-center gap-2 bg-black/70 text-white text-xs px-3 py-1.5 rounded-lg">
                    <span>🔗</span>
                    <span x-text="hs.label"></span>
                    <span class="text-gray-400">→</span>
                    <span x-text="getSceneTitle(hs.target_scene)" class="text-blue-300"></span>
                    <button @click="removeHotspot(i)" class="ml-auto text-red-400 hover:text-red-300 font-bold">✕</button>
                </div>
            </template>
        </div>
    </div>

    {{-- Target scene dialog --}}
    <template x-if="showDialog">
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-sm shadow-2xl space-y-4">
                <h3 class="font-bold text-lg dark:text-white">Configurer le lien</h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pièce de destination</label>
                    <select x-model="newHotspot.target_scene" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white">
                        <template x-for="scene in scenes.filter(s => s.id !== activeSceneId)" :key="scene.id">
                            <option :value="scene.id" x-text="scene.title"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Texte du lien</label>
                    <input
                        x-model="newHotspot.label"
                        type="text"
                        placeholder="ex: Aller à la chambre"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white"
                    />
                </div>

                <div class="flex gap-2">
                    <button
                        @click="confirmHotspot()"
                        :disabled="!newHotspot.target_scene || !newHotspot.label.trim()"
                        class="flex-1 bg-primary-600 text-white py-2 rounded-lg font-medium disabled:opacity-50"
                    >Confirmer</button>
                    <button
                        @click="showDialog = false; isPlacing = false"
                        class="flex-1 border border-gray-300 dark:border-gray-600 rounded-lg py-2 dark:text-white"
                    >Annuler</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Save button --}}
    <button
        @click="saveHotspots()"
        :disabled="isSaving || !activeScene"
        class="w-full py-3 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-semibold rounded-xl transition-colors"
    >
        <span x-text="isSaving ? '⏳ Sauvegarde...' : '💾 Sauvegarder les liens'"></span>
    </button>

    <template x-if="saveSuccess">
        <p class="text-center text-green-600 font-medium">✅ Liens sauvegardés avec succès !</p>
    </template>
</div>

@endif
