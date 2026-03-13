(() => {
    'use strict';

    function parseConfig(root) {
        const configElement = document.getElementById(`${root.id}-config`);
        if (!configElement) {
            return {};
        }
        try {
            return JSON.parse(configElement.textContent || '{}');
        } catch (_) {
            return {};
        }
    }

    function normalizeScenes(root, config) {
        const propertyId = root.dataset.propertyId || '';
        const rawScenes = Array.isArray(config.scenes)
            ? config.scenes
            : (config.scenes && typeof config.scenes === 'object' ? Object.values(config.scenes) : []);

        return rawScenes
            .map((scene, index) => {
                if (!scene || typeof scene !== 'object') {
                    return null;
                }

                const normalizedScene = { ...scene };
                normalizedScene.id = typeof normalizedScene.id === 'string' && normalizedScene.id !== ''
                    ? normalizedScene.id
                    : `scene_${index + 1}`;
                normalizedScene.title = typeof normalizedScene.title === 'string' && normalizedScene.title !== ''
                    ? normalizedScene.title
                    : `Pièce ${index + 1}`;
                normalizedScene.hotspots = Array.isArray(normalizedScene.hotspots) ? normalizedScene.hotspots : [];

                let imagePath = normalizedScene.image_path;
                if (Array.isArray(imagePath)) {
                    imagePath = imagePath[0] || null;
                }

                if ((!normalizedScene.image_url || normalizedScene.image_url.includes('/tour-image/temp/')) && typeof imagePath === 'string' && propertyId) {
                    let relative = imagePath;
                    if (relative.startsWith(`ads/${propertyId}/tours/`)) {
                        relative = relative.replace(`ads/${propertyId}/tours/`, '');
                    } else if (relative.startsWith('ads/temp/tours/')) {
                        relative = relative.replace('ads/temp/tours/', '');
                    } else if (relative.startsWith(`tours/${propertyId}/`)) {
                        relative = relative.replace(`tours/${propertyId}/`, '');
                    }
                    normalizedScene.image_url = `/tour-image/${propertyId}/${relative.replace(/^\/+/, '')}`;
                }

                if (propertyId && typeof normalizedScene.image_url === 'string' && normalizedScene.image_url.includes('/tour-image/temp/')) {
                    normalizedScene.image_url = normalizedScene.image_url.replace('/tour-image/temp/', `/tour-image/${propertyId}/`);
                }

                return normalizedScene;
            })
            .filter(Boolean);
    }

    function initEditor(root) {
        if (!(root instanceof HTMLElement) || root.dataset.khEditorInit === '1') {
            return;
        }
        root.dataset.khEditorInit = '1';

        const config = parseConfig(root);
        const scenes = normalizeScenes(root, config);
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        const propertyId = root.dataset.propertyId || '';

        const firstSceneWithHotspots = scenes.find((scene) => Array.isArray(scene.hotspots) && scene.hotspots.length > 0)?.id ?? null;

        const state = {
            scenes,
            activeSceneId: firstSceneWithHotspots || config.default_scene || (scenes[0]?.id ?? null),
            placing: false,
            pendingCoords: null,
            viewer: null,
            viewerMouseHandler: null,
            fallbackMode: false,
            repositioningIndex: null,
        };

        const tabsContainer = root.querySelector('[data-scene-tabs]');
        const viewerContainer = root.querySelector('[data-viewer]');
        const hotspotList = root.querySelector('[data-hotspot-list]');
        const placingBanner = root.querySelector('[data-placing-banner]');
        const togglePlacingBtn = root.querySelector('[data-toggle-placing]');
        const dialog = root.querySelector('[data-dialog]');
        const targetSceneSelect = root.querySelector('[data-target-scene]');
        const hotspotLabelInput = root.querySelector('[data-hotspot-label]');
        const confirmHotspotBtn = root.querySelector('[data-confirm-hotspot]');
        const cancelHotspotBtn = root.querySelector('[data-cancel-hotspot]');
        const saveHotspotsBtn = root.querySelector('[data-save-hotspots]');
        const feedback = root.querySelector('[data-feedback]');

        const activeScene = () => state.scenes.find((scene) => scene.id === state.activeSceneId) || null;
        const validSceneIds = () => new Set(state.scenes.map((scene) => scene.id));

        const setFeedback = (message, type = 'info') => {
            if (!feedback) return;
            feedback.textContent = message || '';
            feedback.style.color = type === 'error' ? '#dc2626' : (type === 'success' ? '#16a34a' : '#4b5563');
        };

        const updatePlacingUi = () => {
            if (placingBanner) {
                placingBanner.style.display = state.placing ? 'flex' : 'none';
            }
            if (togglePlacingBtn) {
                togglePlacingBtn.style.background = state.placing ? '#f97316' : '#ffffff';
                togglePlacingBtn.style.color = state.placing ? '#ffffff' : '#1f2937';
                if (state.placing && state.repositioningIndex !== null) {
                    togglePlacingBtn.textContent = '🎯 Repositionnement...';
                } else {
                    togglePlacingBtn.textContent = state.placing ? '❌ Annuler' : '➕ Ajouter un lien';
                }
            }
            const image = viewerContainer?.querySelector('img');
            if (image) {
                image.style.cursor = state.placing ? 'crosshair' : 'default';
            }
        };

        const normalizeHotspotsForScene = (scene) => {
            const existing = Array.isArray(scene.hotspots) ? scene.hotspots : [];
            const ids = validSceneIds();

            scene.hotspots = existing
                .map((hotspot) => {
                    if (!hotspot || typeof hotspot !== 'object') {
                        return null;
                    }

                    const targetScene = typeof hotspot.target_scene === 'string'
                        ? hotspot.target_scene
                        : (typeof hotspot.sceneId === 'string' ? hotspot.sceneId : '');
                    const label = typeof hotspot.label === 'string'
                        ? hotspot.label.trim()
                        : (typeof hotspot.text === 'string' ? hotspot.text.trim() : '');

                    const pitch = Number(hotspot.pitch);
                    const yaw = Number(hotspot.yaw);

                    if (!Number.isFinite(pitch) || !Number.isFinite(yaw)) {
                        return null;
                    }
                    if (!targetScene || !ids.has(targetScene)) {
                        return null;
                    }

                    return {
                        pitch: Math.max(-90, Math.min(90, pitch)),
                        yaw: Math.max(-180, Math.min(180, yaw)),
                        target_scene: targetScene,
                        label: label || `Aller vers ${targetScene}`,
                    };
                })
                .filter(Boolean);
        };

        const renderTabs = () => {
            if (!tabsContainer) return;
            tabsContainer.innerHTML = '';
            state.scenes.forEach((scene) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.style.cssText = 'padding:.5rem .75rem;border-radius:.5rem;border:1px solid #d1d5db;background:#fff;color:#111827;font-weight:600;font-size:.875rem;cursor:pointer;';
                if (scene.id === state.activeSceneId) {
                    button.style.background = '#2563eb';
                    button.style.borderColor = '#2563eb';
                    button.style.color = '#fff';
                }
                const hotspotsCount = Array.isArray(scene.hotspots) ? scene.hotspots.length : 0;
                button.textContent = hotspotsCount > 0
                    ? `${scene.title} (${hotspotsCount})`
                    : scene.title;
                button.addEventListener('click', () => {
                    state.activeSceneId = scene.id;
                    state.placing = false;
                    updatePlacingUi();
                    renderTabs();
                    renderScene();
                });
                tabsContainer.appendChild(button);
            });
        };

        const renderHotspotList = () => {
            if (!hotspotList) return;
            hotspotList.innerHTML = '';
            const scene = activeScene();
            if (!scene) return;
            normalizeHotspotsForScene(scene);
            scene.hotspots.forEach((hotspot, index) => {
                const row = document.createElement('div');
                row.style.cssText = 'background:rgba(0,0,0,.65);color:#fff;padding:.3rem .5rem;border-radius:.375rem;margin-top:.25rem;font-size:.75rem;display:flex;align-items:center;gap:.35rem;';
                const target = state.scenes.find((s) => s.id === hotspot.target_scene);
                const text = document.createElement('span');
                text.textContent = `🔗 ${hotspot.label || ''} → ${target ? target.title : hotspot.target_scene}`;
                text.style.flex = '1';

                const moveBtn = document.createElement('button');
                moveBtn.type = 'button';
                moveBtn.textContent = '📍';
                moveBtn.title = 'Repositionner';
                moveBtn.style.cssText = 'border:none;background:rgba(255,255,255,.18);color:#fff;border-radius:.25rem;padding:.1rem .35rem;cursor:pointer;';
                moveBtn.addEventListener('click', () => {
                    state.repositioningIndex = index;
                    state.placing = true;
                    updatePlacingUi();
                    setFeedback('Cliquez un nouveau point dans la scène pour repositionner ce hotspot.', 'info');
                });

                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.textContent = '✕';
                deleteBtn.title = 'Supprimer';
                deleteBtn.style.cssText = 'border:none;background:rgba(239,68,68,.9);color:#fff;border-radius:.25rem;padding:.1rem .35rem;cursor:pointer;';
                deleteBtn.addEventListener('click', () => {
                    scene.hotspots.splice(index, 1);
                    if (state.repositioningIndex === index) {
                        state.repositioningIndex = null;
                        state.placing = false;
                    }
                    updatePlacingUi();
                    renderTabs();
                    renderScene();
                });

                row.appendChild(text);
                row.appendChild(moveBtn);
                row.appendChild(deleteBtn);
                hotspotList.appendChild(row);
            });
        };

        const ensurePannellumLoaded = () => {
            const pannellumCss = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.7/build/pannellum.css';
            const pannellumJsPrimary = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.7/build/pannellum.js';
            const pannellumJsFallback = 'https://unpkg.com/pannellum@2.5.7/build/pannellum.js';

            if (!document.querySelector(`link[href="${pannellumCss}"]`)) {
                const styleTag = document.createElement('link');
                styleTag.rel = 'stylesheet';
                styleTag.href = pannellumCss;
                document.head.appendChild(styleTag);
            }

            if (window.pannellum) {
                return Promise.resolve();
            }

            const loadScript = (src) => new Promise((resolve, reject) => {
                let scriptTag = document.querySelector(`script[src="${src}"]`);
                if (!scriptTag) {
                    scriptTag = document.createElement('script');
                    scriptTag.src = src;
                    document.head.appendChild(scriptTag);
                }
                if (window.pannellum) {
                    resolve();
                    return;
                }
                scriptTag.addEventListener('load', () => resolve(), { once: true });
                scriptTag.addEventListener('error', () => reject(new Error(src)), { once: true });
            });

            return loadScript(pannellumJsPrimary).catch(() => loadScript(pannellumJsFallback));
        };

        const destroyViewer = () => {
            if (!state.viewer) return;
            try {
                if (state.viewerMouseHandler && state.viewer.getContainer) {
                    state.viewer.getContainer().removeEventListener('mousedown', state.viewerMouseHandler);
                }
                state.viewer.destroy();
            } catch (_) {
                // noop
            }
            state.viewer = null;
            state.viewerMouseHandler = null;
        };

        const renderFallbackScene = () => {
            const scene = activeScene();
            if (!scene) {
                setFeedback('Aucune pièce disponible pour lier des hotspots.', 'error');
                return;
            }
            if (!scene.image_url || !viewerContainer) {
                setFeedback('Image de scène introuvable.', 'error');
                return;
            }
            state.fallbackMode = true;
            viewerContainer.innerHTML = '';
            const image = document.createElement('img');
            image.src = scene.image_url;
            image.alt = scene.title;
            image.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
            image.addEventListener('click', (event) => {
                if (!state.placing) return;
                const rect = image.getBoundingClientRect();
                const x = (event.clientX - rect.left) / rect.width;
                const y = (event.clientY - rect.top) / rect.height;
                const nextCoords = { pitch: (0.5 - y) * 180, yaw: (x - 0.5) * 360 };

                if (state.repositioningIndex !== null && scene.hotspots[state.repositioningIndex]) {
                    scene.hotspots[state.repositioningIndex].pitch = nextCoords.pitch;
                    scene.hotspots[state.repositioningIndex].yaw = nextCoords.yaw;
                    state.repositioningIndex = null;
                    state.placing = false;
                    updatePlacingUi();
                    renderScene();
                    setFeedback('✅ Hotspot repositionné. Cliquez sur "Sauvegarder les liens".', 'success');
                    return;
                }

                state.pendingCoords = nextCoords;

                if (!dialog || !targetSceneSelect || !hotspotLabelInput) return;
                targetSceneSelect.innerHTML = '';
                state.scenes.filter((s) => s.id !== scene.id).forEach((s) => {
                    const option = document.createElement('option');
                    option.value = s.id;
                    option.textContent = s.title;
                    targetSceneSelect.appendChild(option);
                });
                hotspotLabelInput.value = '';
                dialog.style.display = 'flex';
            });
            viewerContainer.appendChild(image);
            renderHotspotList();
            setFeedback('Mode image actif: zoomez/déplacez-vous moins précisément qu’en 360°.', 'info');
        };

        const renderScene = () => {
            const scene = activeScene();
            if (!scene) {
                setFeedback('Aucune pièce disponible pour lier des hotspots.', 'error');
                return;
            }
            if (!scene.image_url || !viewerContainer) {
                setFeedback('Image de scène introuvable.', 'error');
                return;
            }

            normalizeHotspotsForScene(scene);

            if (!window.pannellum) {
                renderFallbackScene(scene);
                return;
            }

            state.fallbackMode = false;
            destroyViewer();
            viewerContainer.innerHTML = '';

            try {
                const viewerConfig = {
                    type: 'equirectangular',
                    panorama: scene.image_url,
                    autoLoad: true,
                    hfov: 110,
                    showControls: true,
                    hotSpots: scene.hotspots.map((hotspot, index) => ({
                        pitch: hotspot.pitch,
                        yaw: hotspot.yaw,
                        type: 'custom',
                        text: hotspot.label,
                        cssClass: 'kh-hotspot',
                        id: `hs_${index}`,
                    })),
                };

                if (scene.haov && scene.haov > 0 && scene.haov <= 360) {
                    viewerConfig.haov = scene.haov;
                }
                if (scene.vaov && scene.vaov > 0 && scene.vaov <= 180) {
                    viewerConfig.vaov = scene.vaov;
                    const offset = scene.vOffset || 0;
                    const halfVaov = scene.vaov / 2;
                    viewerConfig.minPitch = -(halfVaov + offset);
                    viewerConfig.maxPitch = halfVaov - offset;
                    if (scene.vaov < 179) {
                        viewerConfig.hfov = Math.min(110, scene.vaov * 0.9);
                        viewerConfig.maxHfov = scene.vaov;
                        viewerConfig.pitch = 0;
                    }
                }
                if (scene.vOffset != null) {
                    viewerConfig.vOffset = scene.vOffset;
                }

                state.viewer = window.pannellum.viewer(viewerContainer, viewerConfig);
            } catch (_) {
                renderFallbackScene(scene);
                return;
            }

            state.viewerMouseHandler = (event) => {
                if (!state.placing || !state.viewer) return;
                if (event instanceof MouseEvent && event.button !== 0) return;
                const coords = state.viewer.mouseEventToCoords(event);
                if (!Array.isArray(coords)) return;
                const nextCoords = {
                    pitch: Number(coords[0]),
                    yaw: Number(coords[1]),
                };
                if (state.repositioningIndex !== null && scene.hotspots[state.repositioningIndex]) {
                    scene.hotspots[state.repositioningIndex].pitch = nextCoords.pitch;
                    scene.hotspots[state.repositioningIndex].yaw = nextCoords.yaw;
                    state.repositioningIndex = null;
                    state.placing = false;
                    updatePlacingUi();
                    renderScene();
                    setFeedback('✅ Hotspot repositionné. Cliquez sur "Sauvegarder les liens".', 'success');
                    return;
                }

                state.pendingCoords = nextCoords;

                if (!dialog || !targetSceneSelect || !hotspotLabelInput) return;
                targetSceneSelect.innerHTML = '';
                state.scenes.filter((s) => s.id !== scene.id).forEach((s) => {
                    const option = document.createElement('option');
                    option.value = s.id;
                    option.textContent = s.title;
                    targetSceneSelect.appendChild(option);
                });
                hotspotLabelInput.value = '';
                dialog.style.display = 'flex';
            };

            state.viewer.getContainer().addEventListener('mousedown', state.viewerMouseHandler);
            renderHotspotList();
            setFeedback(
                scene.hotspots.length > 0
                    ? `Cette pièce contient ${scene.hotspots.length} hotspot(s).`
                    : 'Naviguez dans la scène 360°, puis cliquez pour placer précisément le lien.',
                'info'
            );
        };

        togglePlacingBtn?.addEventListener('click', () => {
            if (state.scenes.length < 2) {
                setFeedback('Ajoutez au moins deux pièces pour créer un lien.', 'error');
                return;
            }
            if (state.placing && state.repositioningIndex !== null) {
                state.repositioningIndex = null;
            }
            state.placing = !state.placing;
            updatePlacingUi();
        });

        cancelHotspotBtn?.addEventListener('click', () => {
            if (dialog) dialog.style.display = 'none';
            state.placing = false;
            state.repositioningIndex = null;
            updatePlacingUi();
        });

        confirmHotspotBtn?.addEventListener('click', () => {
            const scene = activeScene();
            if (!scene || !state.pendingCoords || !targetSceneSelect || !hotspotLabelInput) return;
            const targetScene = targetSceneSelect.value;
            const label = (hotspotLabelInput.value || '').trim();
            if (!targetScene || !label) return;
            scene.hotspots.push({
                pitch: state.pendingCoords.pitch,
                yaw: state.pendingCoords.yaw,
                target_scene: targetScene,
                label,
            });
            state.pendingCoords = null;
            if (dialog) dialog.style.display = 'none';
            state.placing = false;
            updatePlacingUi();
            renderHotspotList();
            renderScene();
        });

        saveHotspotsBtn?.addEventListener('click', async () => {
            const scene = activeScene();
            if (!scene || !propertyId) return;
            normalizeHotspotsForScene(scene);
            saveHotspotsBtn.disabled = true;
            setFeedback('Sauvegarde en cours...');
            try {
                const payloadHotspots = scene.hotspots.map((hotspot) => ({
                    pitch: Number(hotspot.pitch),
                    yaw: Number(hotspot.yaw),
                    target_scene: String(hotspot.target_scene || ''),
                    label: String(hotspot.label || '').trim(),
                }));

                const response = await fetch(`/panel-api/v1/ads/${propertyId}/tour/scenes/${scene.id}/hotspots`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ hotspots: payloadHotspots }),
                });
                if (!response.ok) {
                    let details = '';
                    try {
                        const json = await response.json();
                        const firstError = Object.values(json?.errors || {})?.[0];
                        if (Array.isArray(firstError) && firstError[0]) {
                            details = `: ${firstError[0]}`;
                        }
                    } catch (_) {
                        // noop
                    }
                    throw new Error(`HTTP ${response.status}${details}`);
                }
                setFeedback(' Liens sauvegardés avec succès.', 'success');
            } catch (error) {
                setFeedback(`Erreur lors de la sauvegarde: ${error?.message || 'inconnue'}`, 'error');
            } finally {
                saveHotspotsBtn.disabled = false;
            }
        });

        renderTabs();
        updatePlacingUi();
        ensurePannellumLoaded()
            .then(() => renderScene())
            .catch(() => renderFallbackScene());
    }

    function boot() {
        document.querySelectorAll('[id^="kh-hotspot-editor-"][data-kh-editor="1"]').forEach((root) => {
            initEditor(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }

    const observer = new MutationObserver(() => boot());
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
