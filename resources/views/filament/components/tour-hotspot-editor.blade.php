{{-- resources/views/filament/components/tour-hotspot-editor.blade.php --}}
@php
    $record = $getRecord();
    $tourConfig = $record?->tour_config;
    $hasScenes = is_array($tourConfig) && !empty($tourConfig['scenes']);
    $editorId = 'kh-hotspot-editor-'.($record?->id ?? 'new');
@endphp

@if (!$hasScenes)
    <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 px-4 py-5 flex items-start gap-3">
        <span class="text-amber-500 text-2xl mt-0.5">💾</span>
        <div>
            <p class="font-semibold text-amber-800 dark:text-amber-300 text-sm">Sauvegarde requise pour éditer les liens</p>
            <p class="text-amber-700 dark:text-amber-400 text-sm mt-1">
                Ajoutez vos pièces en Étape 1 puis sauvegardez l'annonce pour activer l'éditeur des hotspots.
            </p>
        </div>
    </div>
@else
    <style>
        .kh-hotspot {
            width: 32px !important;
            height: 32px !important;
            margin-left: -16px !important;
            margin-top: -16px !important;
            background: rgba(255, 255, 255, 0.9) !important;
            border-radius: 50% !important;
            border: 3px solid #3b82f6 !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
        }

        .kh-hotspot::after {
            content: "▶" !important;
            color: #3b82f6 !important;
            font-size: 10px !important;
        }
    </style>

    <div id="{{ $editorId }}" data-kh-editor="1" data-property-id="{{ $record?->id }}" style="display:flex; flex-direction:column; gap:1rem;">
        <p style="font-size:0.875rem; color:#64748b;">
            Sélectionnez une pièce, cliquez sur <strong>Ajouter un lien</strong>, puis cliquez sur la photo pour créer un hotspot.
        </p>

        <div data-scene-tabs style="display:flex; gap:.5rem; flex-wrap:wrap;"></div>

        <div style="position:relative; border-radius:.75rem; overflow:hidden; background:#111827; height:450px;">
            <div data-viewer style="width:100%; height:100%;"></div>

            <div data-placing-banner style="display:none; position:absolute; inset:0; pointer-events:none; z-index:10; border:4px solid #fb923c; border-radius:.75rem; align-items:flex-start; justify-content:center; padding-top:1rem;">
                <div style="background:#f97316; color:#fff; padding:.5rem 1rem; border-radius:.5rem; font-size:.875rem; font-weight:600;">
                    🎯 Cliquez sur la vue pour placer le lien
                </div>
            </div>

            <div style="position:absolute; top:.75rem; right:.75rem; display:flex; gap:.5rem; z-index:20;">
                <button type="button" data-toggle-placing style="padding:.5rem .75rem; border-radius:.5rem; font-size:.875rem; font-weight:600; background:#fff; color:#1f2937; border:none; cursor:pointer;">
                    ➕ Ajouter un lien
                </button>
            </div>

            <div data-hotspot-list style="position:absolute; bottom:.75rem; left:.75rem; z-index:20; max-width:20rem;"></div>
        </div>

        <div data-dialog style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:50; align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:1rem; padding:1.5rem; width:100%; max-width:24rem; box-shadow:0 20px 40px rgba(0,0,0,.2); display:flex; flex-direction:column; gap:1rem;">
                <h3 style="font-weight:700; font-size:1.125rem;">Configurer le lien</h3>

                <div>
                    <label style="display:block; font-size:.875rem; font-weight:600; margin-bottom:.25rem;">Pièce de destination</label>
                    <select data-target-scene style="width:100%; border:1px solid #d1d5db; border-radius:.5rem; padding:.5rem .75rem; font-size:.875rem;"></select>
                </div>

                <div>
                    <label style="display:block; font-size:.875rem; font-weight:600; margin-bottom:.25rem;">Texte du lien</label>
                    <input data-hotspot-label type="text" placeholder="Ex: Aller à la chambre" style="width:100%; border:1px solid #d1d5db; border-radius:.5rem; padding:.5rem .75rem; font-size:.875rem;">
                </div>

                <div style="display:flex; gap:.5rem;">
                    <button type="button" data-confirm-hotspot style="flex:1; background:#2563eb; color:#fff; border:none; border-radius:.5rem; padding:.5rem; font-weight:600; cursor:pointer;">Confirmer</button>
                    <button type="button" data-cancel-hotspot style="flex:1; background:#fff; color:#111827; border:1px solid #d1d5db; border-radius:.5rem; padding:.5rem; cursor:pointer;">Annuler</button>
                </div>
            </div>
        </div>

        <button type="button" data-save-hotspots style="width:50%; margin:0 auto; padding:.75rem; background:#16a34a; color:#fff; font-weight:700; border:none; border-radius:.75rem; cursor:pointer;">
            Sauvegarder
        </button>

        <p data-feedback style="text-align:center; font-size:.875rem; color:#4b5563;"></p>
    </div>

    <script type="application/json" id="{{ $editorId }}-config">@json($tourConfig)</script>
@endif
