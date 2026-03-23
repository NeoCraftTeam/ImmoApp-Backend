<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($flags as $feature => $enabled)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $this->formatLabel($feature) }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $feature }}</p>
                    </div>

                    <span @class([
                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $enabled,
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' => !$enabled,
                    ])>
                        {{ $enabled ? 'ON' : 'OFF' }}
                    </span>
                </div>

                <div class="mt-3 flex gap-2">
                    <x-filament::button
                        size="xs"
                        :color="$enabled ? 'danger' : 'success'"
                        wire:click="toggle('{{ $feature }}')"
                    >
                        {{ $enabled ? 'Disable' : 'Enable' }}
                    </x-filament::button>

                    <x-filament::button
                        size="xs"
                        color="gray"
                        wire:click="resetFlag('{{ $feature }}')"
                    >
                        Reset
                    </x-filament::button>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
