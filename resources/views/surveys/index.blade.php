<x-surveys-layout title="Tous les sondages">
    <h1 class="text-2xl font-black text-gray-900 dark:text-gray-50 mb-1">Sondages & Avis</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Participez à nos sondages. Vos réponses sont entièrement anonymes.</p>

    @if ($surveys->isEmpty())
        <div class="flex flex-col items-center justify-center py-24 text-center">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-6">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <p class="font-bold text-gray-700 dark:text-gray-300">Aucun sondage actif pour le moment</p>
            <p class="text-sm text-gray-400 mt-1">Revenez bientôt !</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($surveys as $survey)
                <a
                    href="{{ route('surveys.show', $survey) }}"
                    class="group block rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm hover:shadow-md hover:border-primary-200 dark:hover:border-primary-700 transition-all duration-200"
                >
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <h2 class="font-bold text-gray-900 dark:text-gray-50 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors leading-snug">
                            {{ $survey->title }}
                        </h2>
                        <span class="shrink-0 text-xs font-semibold px-2 py-1 rounded-lg bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 border border-primary-100 dark:border-primary-700/40 tabular-nums">
                            {{ $survey->questions_count }} question{{ $survey->questions_count > 1 ? 's' : '' }}
                        </span>
                    </div>

                    @if ($survey->description)
                        <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2 mb-4">{{ $survey->description }}</p>
                    @endif

                    <div class="flex items-center gap-1.5 text-sm font-semibold text-primary-600 dark:text-primary-400">
                        Répondre
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-surveys-layout>
