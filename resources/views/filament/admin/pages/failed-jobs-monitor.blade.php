<x-filament-panels::page>
    <div class="mb-6 grid gap-4 md:grid-cols-4">
        @php
            $pending = \Illuminate\Support\Facades\DB::table('jobs')->count();
            $failed = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
            $processed = \Illuminate\Support\Facades\DB::table('jobs')->where('reserved_at', '!=', null)->count();
        @endphp

        <x-filament::section>
            <div class="text-center">
                <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $pending }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Pending Jobs</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $processed }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Processing</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $failed }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Failed Jobs</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-2xl font-bold text-success-600 dark:text-success-400">
                    {{ config('queue.default') }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Queue Driver</p>
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
