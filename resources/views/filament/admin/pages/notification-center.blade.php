<x-filament-panels::page>
    {{ $this->form }}

    <x-filament::section icon="heroicon-o-chart-bar" icon-color="primary">
        <x-slot name="heading">Statistiques des notifications</x-slot>
        @php $stats = $this->getStats(); @endphp
        <div class="grid grid-cols-3 gap-4">
            <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
                <p class="text-2xl font-bold text-primary-600">{{ number_format($stats['total']) }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">Non lues</p>
                <p class="text-2xl font-bold text-warning-600">{{ number_format($stats['unread']) }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">Aujourd'hui</p>
                <p class="text-2xl font-bold text-success-600">{{ number_format($stats['today']) }}</p>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
