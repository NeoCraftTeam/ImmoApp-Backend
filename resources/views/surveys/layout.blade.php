{{--
    Legacy Blade layout aligned with <x-surveys-layout>. Prefer the component for new views.
    Use @section('content') and @extends('surveys.layout') if needed.
--}}
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">
    <title>@yield('title', $title ?? 'Sondages') | KeyHome</title>

    @stack('meta')
    @stack('styles')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800,900" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">

    <a
        href="#survey-main-content"
        class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-gray-900 focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:bg-gray-900 dark:focus:text-gray-50"
    >
        Aller au contenu
    </a>

    <header class="sticky top-0 z-50 border-b border-gray-100 bg-white dark:border-gray-800 dark:bg-gray-900">
        <div class="mx-auto flex h-14 max-w-3xl items-center justify-between px-4">
            <a
                href="{{ route('surveys.index') }}"
                class="-mx-1 inline-flex min-h-11 items-center rounded-md px-1 text-lg font-black tracking-tight text-gray-900 transition-colors hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-50 dark:text-gray-50 dark:hover:text-primary-400 dark:focus-visible:ring-offset-gray-950"
            >
                KeyHome <span class="text-primary-500">Sondages</span>
            </a>

            <span class="hidden items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:border-emerald-700/40 dark:bg-emerald-900/30 dark:text-emerald-400 sm:inline-flex">
                <svg class="h-3 w-3" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" /></svg>
                100&nbsp;% Anonyme
            </span>
        </div>
    </header>

    <main id="survey-main-content" class="mx-auto max-w-3xl px-4 py-10" tabindex="-1">
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-gray-100 py-8 text-center text-xs text-gray-400 dark:border-gray-800 dark:text-gray-600">
        <p>© {{ date('Y') }} KeyHome — Vos réponses ne peuvent pas être reliées à votre identité.</p>
        <div class="mt-2 flex items-center justify-center gap-4">
            <a
                href="{{ route('surveys.index') }}"
                class="rounded-sm transition-colors hover:text-primary-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-50 dark:focus-visible:ring-offset-gray-950"
            >Tous les sondages</a>
            <span aria-hidden="true">·</span>
            <a
                href="/"
                class="rounded-sm transition-colors hover:text-primary-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-50 dark:focus-visible:ring-offset-gray-950"
            >Retour à l'accueil</a>
        </div>
    </footer>

</body>
</html>
