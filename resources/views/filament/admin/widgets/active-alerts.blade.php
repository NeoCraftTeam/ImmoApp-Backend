<x-filament-widgets::widget>
    @if($this->hasActiveAlerts())
        @php $alerts = $this->getAlerts(); @endphp
        <div class="rounded-xl border border-amber-300/60 bg-gradient-to-br from-amber-50 to-orange-50/50 p-5 shadow-sm dark:border-amber-700/40 dark:from-amber-950/30 dark:to-orange-950/20">
            <div class="flex items-center gap-2.5 mb-1.5">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/40">
                    <x-heroicon-m-exclamation-triangle class="h-4.5 w-4.5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200">Alertes actives</h3>
                    <p class="text-xs text-amber-600/80 dark:text-amber-400/70">Points nécessitant votre attention</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 mt-4 sm:grid-cols-3 md:grid-cols-5">
                @if($alerts['inactive_landlords'] > 0)
                    <div class="rounded-xl bg-white/80 p-4 shadow-xs ring-1 ring-gray-100 dark:bg-gray-800/60 dark:ring-gray-700/50">
                        <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 tracking-tight">{{ $alerts['inactive_landlords'] }}</div>
                        <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mt-1">Bailleurs inactifs</div>
                        <div class="text-[10px] leading-tight text-gray-400 dark:text-gray-500 mt-1">Aucune mise à jour d'annonce depuis 30 jours</div>
                    </div>
                @endif

                @if($alerts['low_view_ads'] > 0)
                    <div class="rounded-xl bg-white/80 p-4 shadow-xs ring-1 ring-gray-100 dark:bg-gray-800/60 dark:ring-gray-700/50">
                        <div class="text-2xl font-bold text-orange-600 dark:text-orange-400 tracking-tight">{{ $alerts['low_view_ads'] }}</div>
                        <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mt-1">Annonces invisibles</div>
                        <div class="text-[10px] leading-tight text-gray-400 dark:text-gray-500 mt-1">Aucune vue reçue depuis 14 jours</div>
                    </div>
                @endif

                @if($alerts['fraud_flagged'] > 0)
                    <a href="{{ url('/admin/ad-reports') }}" class="rounded-xl bg-white/80 p-4 shadow-xs ring-1 ring-gray-100 dark:bg-gray-800/60 dark:ring-gray-700/50 hover:ring-2 hover:ring-red-300 dark:hover:ring-red-700 transition-all">
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400 tracking-tight">{{ $alerts['fraud_flagged'] }}</div>
                        <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mt-1">Fraudes suspectées</div>
                        <div class="text-[10px] leading-tight text-gray-400 dark:text-gray-500 mt-1">3+ signalements en 7 jours — Cliquez pour voir</div>
                    </a>
                @endif

                @if($alerts['churn_imminent'] > 0)
                    <div class="rounded-xl bg-white/80 p-4 shadow-xs ring-1 ring-gray-100 dark:bg-gray-800/60 dark:ring-gray-700/50">
                        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 tracking-tight">{{ $alerts['churn_imminent'] }}</div>
                        <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mt-1">Départs probables</div>
                        <div class="text-[10px] leading-tight text-gray-400 dark:text-gray-500 mt-1">Bailleurs supprimant leurs annonces</div>
                    </div>
                @endif

                @if($alerts['revenue_declining'])
                    <div class="rounded-xl bg-white/80 p-4 shadow-xs ring-1 ring-gray-100 dark:bg-gray-800/60 dark:ring-gray-700/50">
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400 tracking-tight">-20%+</div>
                        <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mt-1">Revenus en baisse</div>
                        <div class="text-[10px] leading-tight text-gray-400 dark:text-gray-500 mt-1">Baisse de plus de 20% vs mois dernier</div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
