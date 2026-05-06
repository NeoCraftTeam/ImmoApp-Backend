<x-filament-widgets::widget>
    <x-filament::section
        heading="Export et rapports"
        description="Demandez un export CSV ou PDF des métriques du tableau de bord. La génération s’exécute en file d’attente : une notification Filament avec lien sécurisé apparaît lorsque le fichier est prêt."
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
