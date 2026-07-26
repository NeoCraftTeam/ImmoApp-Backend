<x-filament-panels::page>
    {{ $this->form }}

    <x-filament::section icon="heroicon-o-chart-bar" icon-color="primary">
        <x-slot name="heading">Statistiques des notifications</x-slot>
        @php $stats = $this->getStats(); @endphp
        {{--
            Card surfaces match the project-wide pattern used by
            scheduled-reports / failed-jobs-monitor: rounded-xl + shadow-sm
            + ring-1 with gray-900 dark surface (was rounded-lg + raw shadow
            + dark:bg-gray-800, a one-off older treatment). Numeric values
            now have explicit dark counterparts so they stay legible.
        --}}
        <div class="grid grid-cols-3 gap-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
                <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($stats['total']) }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">Non lues</p>
                <p class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ number_format($stats['unread']) }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">Aujourd'hui</p>
                <p class="text-2xl font-bold text-success-600 dark:text-success-400">{{ number_format($stats['today']) }}</p>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
