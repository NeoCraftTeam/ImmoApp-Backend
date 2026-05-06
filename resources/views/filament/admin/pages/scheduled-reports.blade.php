<x-filament-panels::page>
    @php $data = $this->getReportData(); @endphp

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($data as $key => $item)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $item['value'] }}</p>
            </div>
        @endforeach
    </div>

    <x-filament::section icon="heroicon-o-information-circle" icon-color="info">
        <x-slot name="heading">Exports disponibles</x-slot>
        <x-slot name="description">
            Lancez un export CSV depuis les boutons du bandeau. Chaque export est traité en file d’attente : ouvrez le centre de notifications Filament pour télécharger le fichier (lien valable 24 h).
        </x-slot>
    </x-filament::section>
</x-filament-panels::page>
