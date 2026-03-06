<x-surveys-layout :title="'Merci — ' . $survey->title">
    <div class="flex flex-col items-center justify-center text-center py-12">

        {{-- Animated checkmark --}}
        <div class="relative mb-8">
            <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center shadow-xl shadow-emerald-500/30">
                <svg class="w-12 h-12 text-white [stroke-dasharray:60] [stroke-dashoffset:60] animate-[dash_0.6s_ease-out_0.2s_forwards]"
                    fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"
                    style="stroke-dasharray:60;stroke-dashoffset:60;animation:dash 0.6s ease-out 0.2s forwards;"
                >
                    <style>@keyframes dash { to { stroke-dashoffset: 0; } }</style>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <span class="absolute -bottom-2 -right-2 w-9 h-9 rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 flex items-center justify-center shadow-md text-lg">
                🎉
            </span>
        </div>

        <h1 class="text-2xl font-black text-gray-900 dark:text-gray-50 mb-3">Merci pour votre participation&nbsp;!</h1>

        @if (session('already_submitted'))
            <p class="text-gray-500 dark:text-gray-400 max-w-sm leading-relaxed">
                Vous aviez déjà répondu à ce sondage. Vos réponses originales ont été conservées.
            </p>
        @else
            <p class="text-gray-500 dark:text-gray-400 max-w-sm leading-relaxed">
                Vos réponses ont bien été enregistrées de façon <strong class="text-gray-700 dark:text-gray-300">anonyme</strong>.
                Personne ne peut les relier à votre identité.
            </p>
        @endif

        {{-- Share invite --}}
        <div class="mt-10 w-full max-w-md rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm text-left">
            <p class="font-bold text-gray-800 dark:text-gray-200 mb-1">Invitez vos proches à donner leur avis aussi !</p>
            <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">Plus il y a de participants, plus les résultats sont représentatifs.</p>
            @include('surveys._share', ['survey' => $survey])
        </div>

        <a
            href="/"
            class="mt-8 inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors text-gray-700 dark:text-gray-300"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            Retour à l'accueil
        </a>
    </div>
</x-surveys-layout>
