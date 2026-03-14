<x-filament-widgets::widget>
    <x-filament::section heading="Prix du marché" icon="heroicon-o-chart-bar-square" description="Comparez vos prix avec la moyenne du quartier">
        @if($hasData)
            <div class="space-y-3">
                @foreach($comparisons as $item)
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $item['ad_title'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['quarter_name'] }}, {{ $item['city_name'] }} · {{ $item['active_ads'] }} annonces</p>
                        </div>
                        <div class="ml-4 flex items-center gap-3 text-right">
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($item['ad_price']) }} FCFA</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Moy. {{ number_format($item['avg_price']) }}</p>
                            </div>
                            <span @class([
                                'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $item['diff_percent'] <= 5 && $item['diff_percent'] >= -5,
                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' => $item['diff_percent'] > 5 && $item['diff_percent'] <= 20,
                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' => $item['diff_percent'] > 20,
                                'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' => $item['diff_percent'] < -5,
                            ])>
                                {{ $item['diff_percent'] > 0 ? '+' : '' }}{{ $item['diff_percent'] }}%
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="py-6 text-center">
                <x-heroicon-o-chart-bar-square class="mx-auto h-8 w-8 text-gray-400" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Aucune donnée de prix disponible pour vos quartiers.</p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
