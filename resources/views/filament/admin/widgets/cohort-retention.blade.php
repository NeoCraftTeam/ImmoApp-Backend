<x-filament-widgets::widget>
    <x-filament::section
        heading="Cohortes de rétention"
        description="Ce tableau montre, pour chaque semaine d'inscription, quel pourcentage d'utilisateurs est encore actif après 1, 2, 4, 8 et 12 semaines. Plus le vert est foncé, mieux c'est."
    >
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Semaine d'inscription</th>
                        <th class="px-3 py-2 text-center font-medium text-gray-500 dark:text-gray-400">Nb inscrits</th>
                        @foreach($this->getRetentionWeeks() as $week)
                            <th class="px-3 py-2 text-center font-medium text-gray-500 dark:text-gray-400">{{ $week }} sem. après</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->getCohorts() as $cohort)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $cohort['week'] }}</td>
                            <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">{{ $cohort['cohort_size'] }}</td>
                            @foreach($this->getRetentionWeeks() as $week)
                                @php
                                    $value = $cohort['retention'][$week] ?? null;
                                    $bgClass = match(true) {
                                        $value === null => 'bg-gray-50 dark:bg-gray-900',
                                        $value >= 40 => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300',
                                        $value >= 20 => 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300',
                                        $value >= 10 => 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300',
                                        default => 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
                                    };
                                @endphp
                                <td class="px-3 py-2 text-center rounded {{ $bgClass }}">
                                    {{ $value !== null ? $value.'%' : '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
