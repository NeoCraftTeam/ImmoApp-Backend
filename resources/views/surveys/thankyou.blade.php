@push('styles')
    <style>
        @keyframes survey-thankyou-dash {
            to {
                stroke-dashoffset: 0;
            }
        }

        .survey-thankyou-check {
            stroke-dasharray: 60;
            stroke-dashoffset: 60;
            animation: survey-thankyou-dash 0.6s ease-out 0.2s forwards;
        }

        @media (prefers-reduced-motion: reduce) {
            .survey-thankyou-check {
                animation: none;
                stroke-dashoffset: 0;
            }
        }
    </style>
@endpush

<x-surveys-layout :title="'Merci — ' . $survey->title">
    <div class="flex flex-col items-center justify-center py-12 text-center">

        <div class="relative mb-8">
            <div class="flex h-24 w-24 items-center justify-center rounded-3xl bg-emerald-600 shadow-lg shadow-emerald-900/20 dark:bg-emerald-700">
                <svg
                    class="survey-thankyou-check h-12 w-12 text-white"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <span
                class="absolute -bottom-2 -right-2 flex h-9 w-9 items-center justify-center rounded-2xl border border-gray-100 bg-white text-lg shadow-md dark:border-gray-800 dark:bg-gray-900"
                aria-hidden="true"
            >🎉</span>
        </div>

        <h1 class="mb-3 text-2xl font-black text-gray-900 dark:text-gray-50">Merci pour votre participation&nbsp;!</h1>

        @if (session('already_submitted'))
            <p class="max-w-sm leading-relaxed text-gray-500 dark:text-gray-400">
                Vous aviez déjà répondu à ce sondage. Vos réponses originales ont été conservées.
            </p>
        @else
            <p class="max-w-sm leading-relaxed text-gray-500 dark:text-gray-400">
                Vos réponses ont bien été enregistrées de façon <strong class="text-gray-700 dark:text-gray-300">anonyme</strong>.
                Personne ne peut les relier à votre identité.
            </p>
        @endif

        <div class="mt-10 w-full max-w-md rounded-2xl border border-gray-100 bg-white p-6 text-left shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="mb-1 text-sm font-bold text-gray-800 dark:text-gray-200">Invitez vos proches à donner leur avis aussi&nbsp;!</p>
            <p class="mb-4 text-sm leading-relaxed text-gray-500 dark:text-gray-400">Plus il y a de participants, plus les résultats sont représentatifs.</p>
            @include('surveys._share', ['survey' => $survey])
        </div>

        <a
            href="/"
            class="mt-8 inline-flex min-h-11 items-center gap-2 rounded-xl border border-gray-200 px-6 text-sm font-semibold text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-50 active:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-800/50 dark:focus-visible:ring-offset-gray-950 dark:active:bg-gray-800"
        >
            <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            Retour à l'accueil
        </a>
    </div>
</x-surveys-layout>
