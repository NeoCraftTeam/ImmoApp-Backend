<x-filament-widgets::widget>
    {{-- ═══ MAP SECTION ═══ --}}
    @php
        $mapboxToken = $this->getMapboxToken();
        $mapData = $this->getMapData();
    @endphp

    @if($mapboxToken)
        <x-filament::section
            heading="Carte des annonces par ville"
            description="Chaque cercle représente une ville. La taille indique le nombre d'annonces. Cliquez pour zoomer."
            icon="heroicon-o-map"
            icon-color="primary"
        >
            {{-- Pass large data via script tag — global ID for reliable lookup --}}
            <script type="application/json" id="kh-admin-map-data">
                {!! json_encode(['token' => $mapboxToken, 'cities' => $mapData['cities'], 'ads' => $mapData['ads']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) !!}
            </script>

            <div
                x-data="{
                    map: null,
                    mapStyle: 'light',
                    init() {
                        this.loadMapbox().then(() => this.initMap());
                    },
                    loadMapbox() {
                        return new Promise((resolve) => {
                            if (!document.getElementById('mapbox-gl-css')) {
                                const link = document.createElement('link');
                                link.id = 'mapbox-gl-css';
                                link.rel = 'stylesheet';
                                link.href = 'https://api.mapbox.com/mapbox-gl-js/v3.4.0/mapbox-gl.css';
                                document.head.appendChild(link);
                            }
                            if (window.mapboxgl) return resolve();
                            const s = document.createElement('script');
                            s.src = 'https://api.mapbox.com/mapbox-gl-js/v3.4.0/mapbox-gl.js';
                            s.onload = () => resolve();
                            document.head.appendChild(s);
                        });
                    },
                    initMap() {
                        const raw = document.getElementById('kh-admin-map-data');
                        if (!raw) { console.error('Map data element not found'); return; }
                        const data = JSON.parse(raw.textContent);
                        const token = data.token;
                        const cities = data.cities;
                        const ads = data.ads;

                        mapboxgl.accessToken = token;

                        const isDark = document.documentElement.classList.contains('dark');
                        const style = isDark
                            ? 'mapbox://styles/mapbox/dark-v11'
                            : 'mapbox://styles/mapbox/light-v11';

                        this.map = new mapboxgl.Map({
                            container: this.$refs.mapContainer,
                            style: style,
                            center: [12.3, 5.9],
                            zoom: 5.5,
                            attributionControl: false,
                        });

                        this.map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');
                        this.map.addControl(new mapboxgl.FullscreenControl(), 'top-right');
                        this.map.addControl(new mapboxgl.AttributionControl({ compact: true }), 'bottom-right');

                        this.map.on('load', () => {
                            // — City cluster source —
                            const cityFeatures = cities.map(c => ({
                                type: 'Feature',
                                geometry: { type: 'Point', coordinates: [c.lng, c.lat] },
                                properties: { name: c.name, ad_count: c.ad_count, avg_price: c.avg_price },
                            }));

                            this.map.addSource('cities', {
                                type: 'geojson',
                                data: { type: 'FeatureCollection', features: cityFeatures },
                            });

                            // City circles — size by ad_count
                            this.map.addLayer({
                                id: 'city-circles',
                                type: 'circle',
                                source: 'cities',
                                paint: {
                                    'circle-radius': [
                                        'interpolate', ['linear'], ['get', 'ad_count'],
                                        1, 12,
                                        5, 18,
                                        10, 24,
                                        20, 32,
                                        50, 42,
                                    ],
                                    'circle-color': '#F6475F',
                                    'circle-opacity': 0.75,
                                    'circle-stroke-width': 2,
                                    'circle-stroke-color': '#fff',
                                },
                            });

                            // City labels
                            this.map.addLayer({
                                id: 'city-labels',
                                type: 'symbol',
                                source: 'cities',
                                layout: {
                                    'text-field': ['concat', ['get', 'ad_count'], ''],
                                    'text-font': ['DIN Offc Pro Bold', 'Arial Unicode MS Bold'],
                                    'text-size': 12,
                                    'text-allow-overlap': true,
                                },
                                paint: { 'text-color': '#fff' },
                            });

                            // — Individual ads source (visible only when zoomed in) —
                            const adFeatures = ads.map(a => ({
                                type: 'Feature',
                                geometry: { type: 'Point', coordinates: [a.lng, a.lat] },
                                properties: { id: a.id, title: a.title, price: a.price, city: a.city, quarter: a.quarter },
                            }));

                            this.map.addSource('ads', {
                                type: 'geojson',
                                data: { type: 'FeatureCollection', features: adFeatures },
                            });

                            this.map.addLayer({
                                id: 'ad-points',
                                type: 'circle',
                                source: 'ads',
                                minzoom: 9,
                                paint: {
                                    'circle-radius': 6,
                                    'circle-color': '#F6475F',
                                    'circle-stroke-width': 2,
                                    'circle-stroke-color': '#fff',
                                },
                            });

                            // Fade out city circles when zoomed in
                            this.map.addLayer({
                                id: 'city-circles-faded',
                                type: 'circle',
                                source: 'cities',
                                minzoom: 9,
                                paint: {
                                    'circle-radius': 0,
                                    'circle-opacity': 0,
                                },
                            });

                            // — Interactions —
                            // Popup on city click
                            this.map.on('click', 'city-circles', (e) => {
                                const f = e.features[0];
                                const coords = f.geometry.coordinates.slice();
                                const p = f.properties;
                                const priceFormatted = new Intl.NumberFormat('fr-FR').format(p.avg_price);

                                new mapboxgl.Popup({ offset: 15, maxWidth: '220px' })
                                    .setLngLat(coords)
                                    .setHTML(`
                                        <div style='font-family:system-ui;padding:4px 0'>
                                            <div style='font-weight:700;font-size:14px;margin-bottom:4px'>${p.name}</div>
                                            <div style='font-size:12px;color:#666'>
                                                <strong>${p.ad_count}</strong> annonce${p.ad_count > 1 ? 's' : ''}<br>
                                                Prix moy. <strong>${priceFormatted} FCFA</strong>
                                            </div>
                                        </div>
                                    `)
                                    .addTo(this.map);

                                this.map.easeTo({ center: coords, zoom: Math.max(this.map.getZoom(), 9), duration: 600 });
                            });

                            // Popup on ad click
                            this.map.on('click', 'ad-points', (e) => {
                                const f = e.features[0];
                                const coords = f.geometry.coordinates.slice();
                                const p = f.properties;
                                const priceFormatted = new Intl.NumberFormat('fr-FR').format(p.price);

                                new mapboxgl.Popup({ offset: 10, maxWidth: '240px' })
                                    .setLngLat(coords)
                                    .setHTML(`
                                        <div style='font-family:system-ui;padding:4px 0'>
                                            <div style='font-weight:700;font-size:13px;line-height:1.3;margin-bottom:3px'>${p.title}</div>
                                            <div style='font-size:12px;color:#666'>
                                                ${p.quarter}, ${p.city}<br>
                                                <strong>${priceFormatted} FCFA</strong>
                                            </div>
                                        </div>
                                    `)
                                    .addTo(this.map);
                            });

                            // Cursors
                            this.map.on('mouseenter', 'city-circles', () => this.map.getCanvas().style.cursor = 'pointer');
                            this.map.on('mouseleave', 'city-circles', () => this.map.getCanvas().style.cursor = '');
                            this.map.on('mouseenter', 'ad-points', () => this.map.getCanvas().style.cursor = 'pointer');
                            this.map.on('mouseleave', 'ad-points', () => this.map.getCanvas().style.cursor = '');

                            // Fit bounds to data
                            if (cityFeatures.length > 0) {
                                const bounds = new mapboxgl.LngLatBounds();
                                cityFeatures.forEach(f => bounds.extend(f.geometry.coordinates));
                                this.map.fitBounds(bounds, { padding: 40, maxZoom: 8 });
                            }
                        });
                    },
                    destroy() {
                        if (this.map) { this.map.remove(); this.map = null; }
                    },
                }"
                x-on:destroy="destroy()"
            >
                {{-- Map summary badges --}}
                <div class="flex flex-wrap gap-3 mb-3">
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-primary-50 dark:bg-primary-900/20 px-3 py-1.5 text-sm font-semibold text-primary-700 dark:text-primary-300">
                        <x-heroicon-m-map-pin class="w-4 h-4" />
                        {{ count($mapData['cities']) }} villes
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-success-50 dark:bg-success-900/20 px-3 py-1.5 text-sm font-semibold text-success-700 dark:text-success-300">
                        <x-heroicon-m-home class="w-4 h-4" />
                        {{ count($mapData['ads']) }} annonces
                    </span>
                </div>

                {{-- Mapbox container --}}
                <div
                    x-ref="mapContainer"
                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden"
                    style="height: 420px;"
                ></div>

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Zoomez pour voir les annonces individuelles. Cliquez sur un cercle pour les détails.
                </p>
            </div>
        </x-filament::section>

    @endif

    {{-- ═══ TABLE SECTION ═══ --}}
    <x-filament::section
        heading="Offre vs Demande par quartier"
        description="Comparaison entre le nombre d'annonces disponibles (offre) et les recherches clients (demande). Un ratio élevé signifie que la demande dépasse l'offre."
        icon="heroicon-o-chart-bar"
        icon-color="warning"
    >
        <div class="flex flex-wrap items-center gap-4 mb-4 text-xs text-gray-600 dark:text-gray-400">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> Forte demande</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Demande modérée</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Équilibré</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Plus d'offre que de demande</span>
        </div>

        @php $topZones = $this->getTopUnderserved(); @endphp
        @if(count($topZones) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Quartier</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Ville</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500 dark:text-gray-400">Annonces dispo.</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500 dark:text-gray-400">Recherches clients</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500 dark:text-gray-400">Ratio demande/offre</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Prix moyen</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Évolution prix</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topZones as $zone)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $zone['name'] }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $zone['city'] }}</td>
                                <td class="px-3 py-2 text-center">{{ $zone['supply'] }}</td>
                                <td class="px-3 py-2 text-center">{{ $zone['demand'] }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $zone['ratio'] >= 5 ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' : ($zone['ratio'] >= 2 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300') }}">
                                        {{ $zone['ratio'] }}x
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">{{ number_format($zone['avg_price'], 0, ',', ' ') }} FCFA</td>
                                <td class="px-3 py-2 text-right {{ $zone['price_trend'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($zone['price_trend'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500') }}">
                                    {{ $zone['price_trend'] > 0 ? '+' : '' }}{{ $zone['price_trend'] }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400">
                <span class="font-medium text-gray-600 dark:text-gray-300">Lecture :</span>
                Un ratio de 5x signifie qu'il y a 5 fois plus de demande que d'offre dans ce quartier.
                Les zones en rouge sont celles où il manque le plus de logements.
            </div>
        @else
            <p class="text-gray-500 dark:text-gray-400 text-center py-8">Aucune donnée géographique disponible pour le moment</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
