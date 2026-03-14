<x-filament-widgets::widget>
    <x-filament::section
        heading="Export et rapports"
        description="Téléchargez toutes les métriques du tableau de bord au format CSV (pour Excel / Google Sheets) ou PDF (pour vos présentations et rapports investisseurs)."
    >
        <div class="flex flex-wrap gap-3">
            <x-filament::button
                wire:click="exportCsv"
                icon="heroicon-o-table-cells"
                color="gray"
            >
                Télécharger en CSV
            </x-filament::button>

            <x-filament::button
                wire:click="exportPdf"
                icon="heroicon-o-document-arrow-down"
                color="info"
            >
                Télécharger en PDF
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
