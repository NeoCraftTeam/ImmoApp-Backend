<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">
    <title>{{ $title ?? 'Sondages' }} | KeyHome</title>

    @stack('meta')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800,900" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">

    {{-- Top nav bar --}}
    <header class="sticky top-0 z-50 bg-white/80 dark:bg-gray-900/80 backdrop-blur border-b border-gray-100 dark:border-gray-800">
        <div class="max-w-3xl mx-auto px-4 h-14 flex items-center justify-between">
            <a href="{{ route('surveys.index') }}" class="font-black text-lg tracking-tight text-gray-900 dark:text-gray-50">
                KeyHome <span class="text-primary-500">Sondages</span>
            </a>

            <span class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700/40">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" /></svg>
                100&nbsp;% Anonyme
            </span>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-10">
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="border-t border-gray-100 dark:border-gray-800 mt-16 py-8 text-center text-xs text-gray-400 dark:text-gray-600">
        <p>© {{ date('Y') }} KeyHome — Vos réponses ne peuvent pas être reliées à votre identité.</p>
        <div class="mt-2 flex items-center justify-center gap-4">
            <a href="{{ route('surveys.index') }}" class="hover:text-primary-500 transition-colors">Tous les sondages</a>
            <span aria-hidden="true">·</span>
            <a href="/" class="hover:text-primary-500 transition-colors">Retour à l'accueil</a>
        </div>
    </footer>

</body>
</html>
