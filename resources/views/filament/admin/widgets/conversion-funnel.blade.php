<x-filament-widgets::widget>
    <x-filament::section
        heading="Tunnel de conversion"
        description="Parcours des utilisateurs étape par étape, du premier visit jusqu'à la signature d'un bail. Le pourcentage indique la conversion par rapport à l'étape précédente."
    >
        @php $funnel = $this->getFunnelData(); @endphp
        @if(!empty($funnel['steps']))
            @php
                $counts = array_column($funnel['steps'], 'count');
                $maxCount = max(max($counts), 1);
            @endphp
            <div class="space-y-3">
                @foreach($funnel['steps'] as $i => $step)
                    @php
                        $width = $maxCount > 0 ? max(($step['count'] / $maxCount) * 100, 4) : 4;
                        $colorClass = match(true) {
                            $i === 0 => 'bg-blue-500',
                            $i === 1 => 'bg-indigo-500',
                            $i === 2 => 'bg-violet-500',
                            $i === 3 => 'bg-purple-500',
                            $i === 4 => 'bg-fuchsia-500',
                            default => 'bg-emerald-500',
                        };
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $step['label'] }}
                            </span>
                            <div class="flex items-center gap-3 text-sm">
                                <span class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format($step['count']) }}
                                </span>
                            </div>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-6 overflow-hidden">
                            <div class="{{ $colorClass }} h-6 rounded-full transition-all duration-500 flex items-center justify-end pr-2"
                                 style="width: {{ $width }}%">
                                @if($width > 15)
                                    <span class="text-xs font-medium text-white">{{ number_format($step['count']) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400">
                <span class="font-medium text-gray-600 dark:text-gray-300">Lecture :</span>
                Chaque barre représente une étape du parcours utilisateur.
                Le pourcentage « convertis » indique combien passent à l'étape suivante.
                Le pourcentage « perdus » indique combien abandonnent.
            </div>
        @else
            <p class="text-gray-500 dark:text-gray-400 text-center py-8">Aucune donnée disponible pour le moment</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
