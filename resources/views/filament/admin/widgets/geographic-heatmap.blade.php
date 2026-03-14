<x-filament-widgets::widget>
    <x-filament::section
        heading="Carte géographique — Offre vs Demande"
        description="Comparaison entre le nombre d'annonces disponibles (offre) et les recherches clients (demande) par quartier. Un ratio élevé signifie que la demande dépasse l'offre."
    >
        @php $geoData = $this->getGeoData(); @endphp

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
