@php
    /** @var \Filament\Infolists\Components\ViewEntry $entry */
    $record = $entry->getLivewire()->record;
    $url = config('app.frontend_url') . '/surveys/' . $record->slug;
@endphp

<div
    x-data="{ copied: false, url: @js($url) }"
    class="flex flex-wrap items-center gap-2"
>
    {{-- Open in new tab --}}
    <a
        href="{{ $url }}"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1"
    >
        <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="size-4" />
        Ouvrir le sondage
    </a>

    {{-- Copy to clipboard --}}
    <button
        type="button"
        x-on:click="
            navigator.clipboard.writeText(url).then(() => {
                copied = true;
                setTimeout(() => { copied = false }, 2000);
            });
        "
        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
    >
        <template x-if="!copied">
            <x-filament::icon icon="heroicon-o-clipboard-document-list" class="size-4" />
        </template>
        <template x-if="copied">
            <x-filament::icon icon="heroicon-o-check" class="size-4 text-green-500" />
        </template>
        <span x-text="copied ? 'Copié !' : 'Copier le lien'"></span>
    </button>

    {{-- URL preview --}}
    <span class="hidden truncate text-xs text-gray-400 font-mono sm:inline-block max-w-xs" title="{{ $url }}">
        {{ $url }}
    </span>
</div>
