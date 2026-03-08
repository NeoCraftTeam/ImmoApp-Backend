@php
    /** @var \Filament\Infolists\Components\ViewEntry $entry */
    $livewire = $entry->getLivewire();
    $respondents = $livewire->getRespondentsWithAnswers();
    $total = $respondents->count();

    $palettes = [
        ['from' => 'from-violet-500', 'to' => 'to-purple-600',  'badge' => 'bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-300 border-violet-200 dark:border-violet-500/20'],
        ['from' => 'from-sky-500',    'to' => 'to-blue-600',    'badge' => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-500/20'],
        ['from' => 'from-emerald-500','to' => 'to-teal-600',    'badge' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/20'],
        ['from' => 'from-rose-500',   'to' => 'to-pink-600',    'badge' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/20'],
        ['from' => 'from-amber-500',  'to' => 'to-orange-600',  'badge' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/20'],
        ['from' => 'from-indigo-500', 'to' => 'to-blue-700',    'badge' => 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-500/20'],
        ['from' => 'from-fuchsia-500','to' => 'to-purple-700',  'badge' => 'bg-fuchsia-50 dark:bg-fuchsia-500/10 text-fuchsia-700 dark:text-fuchsia-300 border-fuchsia-200 dark:border-fuchsia-500/20'],
        ['from' => 'from-cyan-500',   'to' => 'to-sky-600',     'badge' => 'bg-cyan-50 dark:bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border-cyan-200 dark:border-cyan-500/20'],
    ];

    $allRatings = $respondents->flatMap(fn ($r) => collect($r['answers'])
        ->filter(fn ($a) => $a['type'] === 'rating' && $a['has_answer'])
        ->map(fn ($a) => (int) preg_replace('/\D.*/', '', $a['answer']))
    );
    $avgRating = $allRatings->count() && $allRatings->avg() > 0 ? round($allRatings->avg(), 1) : null;

    $identified = $respondents->where('is_anonymous', false)->values();
    $anonymous  = $respondents->where('is_anonymous', true)->values();
    $newResponses = $respondents->where('is_new', true)->values();
@endphp

@if ($respondents->isEmpty())
    <div class="flex flex-col items-center justify-center py-16 text-center select-none">
        <div class="mb-5 w-16 h-16 rounded-2xl bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-750 flex items-center justify-center">
            <x-filament::icon icon="heroicon-o-inbox" class="w-8 h-8 text-gray-300 dark:text-gray-600" />
        </div>
        <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-1">Aucune réponse pour l'instant</p>
        <p class="text-xs text-gray-400 dark:text-gray-500 max-w-[260px]">Les réponses apparaîtront ici dès que le sondage sera complété.</p>
    </div>
@else
    {{-- ═══ Stats ═══════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-8">
        <div class="rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 p-4 text-white shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <x-filament::icon icon="heroicon-s-users" class="w-5 h-5 opacity-80" />
                <span class="text-[10px] font-semibold uppercase tracking-wider opacity-70">Répondants</span>
            </div>
            <p class="text-3xl font-black tabular-nums leading-none">{{ $total }}</p>
            <div class="mt-2 flex gap-2">
                @if ($identified->count() > 0)
                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded bg-white/20">{{ $identified->count() }} identifié{{ $identified->count() > 1 ? 's' : '' }}</span>
                @endif
                @if ($anonymous->count() > 0)
                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded bg-white/20">{{ $anonymous->count() }} anonyme{{ $anonymous->count() > 1 ? 's' : '' }}</span>
                @endif
            </div>
        </div>

        <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <x-filament::icon icon="heroicon-s-star" class="w-5 h-5 text-amber-400" />
                <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Note moyenne</span>
            </div>
            @if ($avgRating !== null)
                <div class="flex items-baseline gap-1">
                    <p class="text-3xl font-black text-gray-900 dark:text-gray-50 tabular-nums leading-none">{{ $avgRating }}</p>
                    <span class="text-sm font-semibold text-gray-300 dark:text-gray-600">/5</span>
                </div>
                <div class="mt-2 flex gap-0.5">
                    @for ($s = 1; $s <= 5; $s++)
                        @if ($s <= round($avgRating))
                            <x-filament::icon icon="heroicon-s-star" class="w-3.5 h-3.5 text-amber-400" />
                        @else
                            <x-filament::icon icon="heroicon-o-star" class="w-3.5 h-3.5 text-gray-200 dark:text-gray-700" />
                        @endif
                    @endfor
                </div>
            @else
                <p class="text-3xl font-black text-gray-200 dark:text-gray-700 tabular-nums leading-none">—</p>
                <p class="mt-2 text-[10px] text-gray-300 dark:text-gray-600">Aucune note</p>
            @endif
        </div>

        <div class="col-span-2 sm:col-span-1 rounded-xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <x-filament::icon icon="heroicon-s-chart-bar" class="w-5 h-5 text-violet-500" />
                <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Participation</span>
            </div>
            <div class="space-y-2">
                <div>
                    <div class="flex justify-between text-[10px] mb-1">
                        <span class="font-medium text-gray-500 dark:text-gray-400">Identifiés</span>
                        <span class="font-bold text-gray-700 dark:text-gray-300">{{ $identified->count() }}</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-teal-500" style="width: {{ $total > 0 ? round(($identified->count() / $total) * 100) : 0 }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-[10px] mb-1">
                        <span class="font-medium text-gray-500 dark:text-gray-400">Anonymes</span>
                        <span class="font-bold text-gray-700 dark:text-gray-300">{{ $anonymous->count() }}</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-gray-300 to-gray-400 dark:from-gray-600 dark:to-gray-500" style="width: {{ $total > 0 ? round(($anonymous->count() / $total) * 100) : 0 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ New / Unread Responses ═════════════════════════════════════════ --}}
    @if ($newResponses->count() > 0)
        <div class="mb-8">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                    </span>
                    Nouvelles réponses
                    <span class="text-[10px] font-bold text-white bg-rose-500 rounded-full px-1.5 py-0.5 tabular-nums leading-none">{{ $newResponses->count() }}</span>
                </h3>
                <button
                    type="button"
                    wire:click="markAllAsViewed"
                    class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 transition-colors"
                >
                    <x-filament::icon icon="heroicon-o-check-circle" class="w-4 h-4" />
                    Tout marquer comme lu
                </button>
            </div>

            <div class="rounded-xl border-2 border-dashed border-rose-200 dark:border-rose-500/20 bg-rose-50/30 dark:bg-rose-500/5 overflow-hidden">
                <div class="divide-y divide-rose-100 dark:divide-rose-500/10">
                    @foreach ($newResponses as $index => $respondent)
                        @php $p = $palettes[$index % count($palettes)]; @endphp
                        <div class="flex items-center gap-3 px-4 py-3 group" wire:key="new-{{ $respondent['user_id'] }}">
                            @if ($respondent['avatar'])
                                <img src="{{ $respondent['avatar'] }}" alt="" class="w-8 h-8 rounded-lg object-cover shrink-0" />
                            @else
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br {{ $p['from'] }} {{ $p['to'] }} flex items-center justify-center shrink-0">
                                    <span class="text-[11px] font-black text-white">{{ Str::upper(Str::substr($respondent['display_name'], 0, 1)) }}</span>
                                </div>
                            @endif

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">{{ $respondent['display_name'] }}</span>
                                    @if ($respondent['is_anonymous'])
                                        <span class="text-[8px] font-bold uppercase px-1.5 py-0.5 rounded-full bg-gray-200/60 dark:bg-gray-700/60 text-gray-500 dark:text-gray-400">Anonyme</span>
                                    @endif
                                </div>
                                <p class="text-[10px] text-gray-400 dark:text-gray-600 truncate">{{ $respondent['email'] }}</p>
                            </div>

                            <span class="text-[10px] text-gray-400 tabular-nums whitespace-nowrap hidden sm:block">{{ $respondent['submitted_at'] }}</span>

                            <button
                                type="button"
                                wire:click="markAsViewed('{{ $respondent['is_anonymous'] ? 'anonymous' : 'authenticated' }}', '{{ $respondent['is_anonymous'] ? ($respondent['anon_response_id'] ?? '') : implode(',', $respondent['response_ids'] ?? []) }}')"
                                class="shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors"
                                title="Marquer comme lu"
                            >
                                <x-filament::icon icon="heroicon-o-eye" class="w-4 h-4" />
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ Split View: Identified | Anonymous ═════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- ── Left: Identified Responses ──────────────────────────────── --}}
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-6 h-6 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center">
                    <x-filament::icon icon="heroicon-s-user" class="w-3.5 h-3.5 text-emerald-500" />
                </span>
                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200">Réponses identifiées</h3>
                <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-1.5 py-0.5 rounded-full tabular-nums">{{ $identified->count() }}</span>
            </div>

            @if ($identified->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-8 text-center">
                    <x-filament::icon icon="heroicon-o-user-group" class="w-6 h-6 text-gray-300 dark:text-gray-600 mx-auto mb-2" />
                    <p class="text-xs text-gray-400 dark:text-gray-500">Aucune réponse identifiée</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($identified as $index => $respondent)
                        @php
                            $p = $palettes[$index % count($palettes)];
                            $initial = Str::upper(Str::substr($respondent['display_name'], 0, 1));
                        @endphp
                        <div
                            x-data="{ open: false }"
                            class="rounded-xl border bg-white dark:bg-gray-900 overflow-hidden transition-all duration-200"
                            :class="open
                                ? 'shadow-md border-emerald-200 dark:border-emerald-500/20 ring-1 ring-emerald-100 dark:ring-emerald-500/10'
                                : 'border-gray-100 dark:border-gray-800 hover:border-gray-200 dark:hover:border-gray-700'"
                        >
                            <button type="button" @click="open = !open" class="w-full flex items-center gap-3 px-4 py-3 text-left group" :aria-expanded="open">
                                @if ($respondent['avatar'])
                                    <img src="{{ $respondent['avatar'] }}" alt="" class="w-9 h-9 rounded-lg object-cover shrink-0" />
                                @else
                                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br {{ $p['from'] }} {{ $p['to'] }} flex items-center justify-center shadow-sm shrink-0">
                                        <span class="text-xs font-black text-white select-none">{{ $initial }}</span>
                                    </div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <span class="text-xs font-bold text-gray-800 dark:text-gray-100 truncate block">{{ $respondent['display_name'] }}</span>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">{{ $respondent['email'] }}</p>
                                </div>
                                @if ($respondent['is_new'])
                                    <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0" title="Nouveau"></span>
                                @endif
                                <span class="text-[10px] text-gray-400 tabular-nums whitespace-nowrap hidden sm:block">{{ $respondent['submitted_at'] }}</span>
                                <span class="shrink-0 transition-transform duration-200 text-gray-300 dark:text-gray-700 group-hover:text-gray-500" :class="{ 'rotate-180 !text-emerald-500': open }">
                                    <x-filament::icon icon="heroicon-s-chevron-down" class="w-3.5 h-3.5" />
                                </span>
                            </button>

                            <div x-show="open" x-collapse>
                                @include('filament.surveys.partials.respondent-answers', ['respondent' => $respondent, 'palette' => $p])
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Right: Anonymous Responses ──────────────────────────────── --}}
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                    <x-filament::icon icon="heroicon-s-eye-slash" class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" />
                </span>
                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200">Réponses anonymes</h3>
                <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded-full tabular-nums">{{ $anonymous->count() }}</span>
            </div>

            @if ($anonymous->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-8 text-center">
                    <x-filament::icon icon="heroicon-o-eye-slash" class="w-6 h-6 text-gray-300 dark:text-gray-600 mx-auto mb-2" />
                    <p class="text-xs text-gray-400 dark:text-gray-500">Aucune réponse anonyme</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($anonymous as $index => $respondent)
                        @php
                            $p = $palettes[($index + 3) % count($palettes)];
                            $initial = Str::upper(Str::substr($respondent['display_name'], 0, 1));
                        @endphp
                        <div
                            x-data="{ open: false }"
                            class="rounded-xl border bg-white dark:bg-gray-900 overflow-hidden transition-all duration-200"
                            :class="open
                                ? 'shadow-md border-gray-300 dark:border-gray-600 ring-1 ring-gray-200 dark:ring-gray-700'
                                : 'border-gray-100 dark:border-gray-800 hover:border-gray-200 dark:hover:border-gray-700'"
                        >
                            <button type="button" @click="open = !open" class="w-full flex items-center gap-3 px-4 py-3 text-left group" :aria-expanded="open">
                                <div class="w-9 h-9 rounded-lg bg-gradient-to-br {{ $p['from'] }} {{ $p['to'] }} flex items-center justify-center shadow-sm shrink-0">
                                    <span class="text-xs font-black text-white select-none">{{ $initial }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="text-xs font-bold text-gray-800 dark:text-gray-100 truncate block">{{ $respondent['display_name'] }}</span>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">{{ $respondent['email'] }}</p>
                                </div>
                                @if ($respondent['is_new'])
                                    <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0" title="Nouveau"></span>
                                @endif
                                <span class="text-[10px] text-gray-400 tabular-nums whitespace-nowrap hidden sm:block">{{ $respondent['submitted_at'] }}</span>
                                <span class="shrink-0 transition-transform duration-200 text-gray-300 dark:text-gray-700 group-hover:text-gray-500" :class="{ 'rotate-180 !text-gray-500': open }">
                                    <x-filament::icon icon="heroicon-s-chevron-down" class="w-3.5 h-3.5" />
                                </span>
                            </button>

                            <div x-show="open" x-collapse>
                                @include('filament.surveys.partials.respondent-answers', ['respondent' => $respondent, 'palette' => $p])
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <p class="mt-6 text-center text-[10px] text-gray-300 dark:text-gray-700 select-none tabular-nums">
        {{ $total }} {{ $total > 1 ? 'participants' : 'participant' }}
    </p>
@endif
