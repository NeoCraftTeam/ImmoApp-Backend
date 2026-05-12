<x-filament-panels::page>
    <div class="mb-6 grid gap-4 md:grid-cols-4">
        @php
            $totalJobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
            $processing = \Illuminate\Support\Facades\DB::table('jobs')->whereNotNull('reserved_at')->count();
            $pending = max(0, $totalJobs - $processing);
            $failed = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
            $driver = config('queue.default');
        @endphp

        <x-filament::section>
            <div class="text-center">
                <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $pending }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Jobs en attente</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $processing }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">En cours d'exécution</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $failed }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Jobs échoués</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-2xl font-bold text-success-600 dark:text-success-400 capitalize">
                    {{ $driver }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Pilote de file</p>
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
