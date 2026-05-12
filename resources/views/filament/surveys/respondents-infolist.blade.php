@php
    /** @var \Filament\Infolists\Components\ViewEntry $entry */
    $livewire = $entry->getLivewire();
    $respondents = $livewire->getRespondentsWithAnswers();
    $total = $respondents->count();

    $palettes = [
        ['solid' => 'bg-violet-600 dark:bg-violet-500', 'badge' => 'bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-300 border-violet-200 dark:border-violet-500/20'],
        ['solid' => 'bg-sky-600 dark:bg-sky-500', 'badge' => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-500/20'],
        ['solid' => 'bg-emerald-600 dark:bg-emerald-500', 'badge' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/20'],
        ['solid' => 'bg-rose-600 dark:bg-rose-500', 'badge' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/20'],
        ['solid' => 'bg-amber-600 dark:bg-amber-500', 'badge' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/20'],
        ['solid' => 'bg-indigo-600 dark:bg-indigo-500', 'badge' => 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-500/20'],
        ['solid' => 'bg-fuchsia-600 dark:bg-fuchsia-500', 'badge' => 'bg-fuchsia-50 dark:bg-fuchsia-500/10 text-fuchsia-700 dark:text-fuchsia-300 border-fuchsia-200 dark:border-fuchsia-500/20'],
        ['solid' => 'bg-cyan-600 dark:bg-cyan-500', 'badge' => 'bg-cyan-50 dark:bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border-cyan-200 dark:border-cyan-500/20'],
    ];

    $allRatings = $respondents->flatMap(fn ($r) => collect($r['answers'])
        ->filter(fn ($a) => $a['type'] === 'rating' && $a['has_answer'])
        ->map(fn ($a) => (int) preg_replace('/\D.*/', '', $a['answer']))
    );
    $avgRating    = $allRatings->count() && $allRatings->avg() > 0 ? round($allRatings->avg(), 1) : null;
    $identified   = $respondents->where('is_anonymous', false)->values();
    $anonymous    = $respondents->where('is_anonymous', true)->values();
    $newResponses = $respondents->where('is_new', true)->values();
    $newCount     = $newResponses->count();
@endphp

@if ($respondents->isEmpty())
    {{-- ── Empty state ────────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col items-center justify-center py-20 text-center select-none">
        <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800 shadow-inner">
            <x-filament::icon icon="heroicon-o-inbox" class="w-8 h-8 text-gray-300 dark:text-gray-600" />
        </div>
        <p class="text-sm font-bold text-gray-600 dark:text-gray-400 mb-1">Aucune réponse pour l\'instant</p>
        <p class="text-xs text-gray-400 dark:text-gray-500 max-w-[280px] leading-relaxed">Les réponses apparaissent ici dès que le sondage est complété par un participant.</p>
    </div>
@else
    <div
        x-data="{
            tab: 'all',
            tabs: [
                { key: 'all',     label: 'Tous',        count: {{ $total }} },
                { key: 'identified', label: 'Identifiés', count: {{ $identified->count() }} },
                { key: 'anonymous',  label: 'Anonymes',    count: {{ $anonymous->count() }} },
            ]
        }"
    >
        {{-- ═══ Stats grid ═══════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">

            {{-- Total respondents --}}
            <div class="col-span-2 sm:col-span-1 rounded-2xl bg-gradient-to-br from-primary-600 to-primary-700 dark:from-primary-500 dark:to-primary-600 p-4 text-white shadow-lg shadow-primary-500/20">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/20">
                        <x-filament::icon icon="heroicon-s-users" class="h-4 w-4" />
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-widest opacity-80">Participants</span>
                </div>
                <p class="text-4xl font-black tabular-nums leading-none tracking-tight">{{ $total }}</p>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @if ($identified->count() > 0)
                        <span class="inline-flex items-center gap-1 text-[9px] font-bold px-2 py-0.5 rounded-full bg-white/20">
                            <x-filament::icon icon="heroicon-s-user" class="w-2.5 h-2.5" />
                            {{ $identified->count() }} identifié{{ $identified->count() > 1 ? 's' : '' }}
                        </span>
                    @endif
                    @if ($anonymous->count() > 0)
                        <span class="inline-flex items-center gap-1 text-[9px] font-bold px-2 py-0.5 rounded-full bg-white/20">
                            <x-filament::icon icon="heroicon-s-eye-slash" class="w-2.5 h-2.5" />
                            {{ $anonymous->count() }} anonyme{{ $anonymous->count() > 1 ? 's' : '' }}
                        </span>
                    @endif
                    @if ($newCount > 0)
                        <span class="inline-flex items-center gap-1 text-[9px] font-bold px-2 py-0.5 rounded-full bg-rose-500/80">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                            {{ $newCount }} non lu{{ $newCount > 1 ? 's' : '' }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Avg rating --}}
            <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-500/10">
                        <x-filament::icon icon="heroicon-s-star" class="w-4 h-4 text-amber-400" />
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Note moy.</span>
                </div>
                @if ($avgRating !== null)
                    <div class="flex items-baseline gap-1">
                        <span class="text-3xl font-black tabular-nums text-gray-900 dark:text-white leading-none">{{ $avgRating }}</span>
                        <span class="text-xs font-semibold text-gray-300 dark:text-gray-600">/5</span>
                    </div>
                    <div class="mt-2.5 flex gap-0.5">
                        @for ($s = 1; $s <= 5; $s++)
                            @if ($s <= round($avgRating))
                                <x-filament::icon icon="heroicon-s-star" class="w-3.5 h-3.5 text-amber-400" />
                            @else
                                <x-filament::icon icon="heroicon-o-star" class="w-3.5 h-3.5 text-gray-200 dark:text-gray-700" />
                            @endif
                        @endfor
                    </div>
                @else
                    <p class="text-3xl font-black text-gray-200 dark:text-gray-700 leading-none">—</p>
                    <p class="mt-2.5 text-[10px] text-gray-300 dark:text-gray-600">Pas de note</p>
                @endif
            </div>

            {{-- Identified --}}
            <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-500/10">
                        <x-filament::icon icon="heroicon-s-user-circle" class="w-4 h-4 text-emerald-500" />
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Identifiés</span>
                </div>
                <p class="text-3xl font-black tabular-nums text-gray-900 dark:text-white leading-none">{{ $identified->count() }}</p>
                <div class="mt-2.5 h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $total > 0 ? round(($identified->count() / $total) * 100) : 0 }}%"></div>
                </div>
            </div>

            {{-- Anonymous --}}
            <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                        <x-filament::icon icon="heroicon-s-eye-slash" class="w-4 h-4 text-gray-400" />
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Anonymes</span>
                </div>
                <p class="text-3xl font-black tabular-nums text-gray-900 dark:text-white leading-none">{{ $anonymous->count() }}</p>
                <div class="mt-2.5 h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                    <div class="h-full bg-gray-400 dark:bg-gray-500 rounded-full" style="width: {{ $total > 0 ? round(($anonymous->count() / $total) * 100) : 0 }}%"></div>
                </div>
            </div>
        </div>

        {{-- ═══ Unread banner ═════════════════════════════════════════════════════════ --}}
        @if ($newCount > 0)
            <div class="mb-6 flex items-center justify-between rounded-2xl border border-rose-200 dark:border-rose-500/20 bg-rose-50/60 dark:bg-rose-500/5 px-4 py-3">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-3 w-3 shrink-0">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75 motion-reduce:hidden"></span>
                        <span class="relative inline-flex h-3 w-3 rounded-full bg-rose-500"></span>
                    </span>
                    <span class="text-sm font-bold text-rose-700 dark:text-rose-400">
                        {{ $newCount }} nouvelle{{ $newCount > 1 ? 's' : '' }} réponse{{ $newCount > 1 ? 's' : '' }} non lue{{ $newCount > 1 ? 's' : '' }}
                    </span>
                </div>
                <button
                    type="button"
                    wire:click="markAllAsViewed"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-3 py-1.5 text-[11px] font-bold text-white transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-1"
                >
                    <x-filament::icon icon="heroicon-o-check" class="w-3.5 h-3.5" />
                    Tout marquer comme lu
                </button>
            </div>
        @endif

        {{-- ═══ Tab bar ═════════════════════════════════════════════════════───────── --}}
        <div class="mb-5 flex gap-1 rounded-2xl bg-gray-100 dark:bg-gray-800 p-1">
            <template x-for="t in tabs" :key="t.key">
                <button
                    type="button"
                    @click="tab = t.key"
                    class="flex-1 flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold transition-all duration-200"
                    :class="tab === t.key
                        ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                >
                    <span x-text="t.label"></span>
                    <span
                        class="min-w-[1.25rem] rounded-full px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-center leading-none"
                        :class="tab === t.key
                            ? 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'
                            : 'bg-gray-200/80 dark:bg-gray-700/80 text-gray-500 dark:text-gray-400'"
                        x-text="t.count"
                    ></span>
                </button>
            </template>
        </div>

        {{-- ═══ Respondent list (all) ═════════════════════════════════════════════───── --}}
        @foreach ($respondents as $index => $respondent)
            @php
                $p           = $palettes[$index % count($palettes)];
                $initial     = Str::upper(Str::substr($respondent['display_name'], 0, 1));
                $answeredCnt = collect($respondent['answers'])->where('has_answer', true)->count();
                $totalCnt    = count($respondent['answers']);
                $completePct = $totalCnt > 0 ? round(($answeredCnt / $totalCnt) * 100) : 0;
                $tabKey      = $respondent['is_anonymous'] ? 'anonymous' : 'identified';
            @endphp
            <div
                x-show="tab === 'all' || tab === '{{ $tabKey }}'"
                x-data="{ open: false }"
                class="mb-2 rounded-2xl border bg-white dark:bg-gray-900 overflow-hidden transition-all duration-200"
                :class="open
                    ? 'shadow-md border-{{ $respondent['is_anonymous'] ? 'gray-300 dark:border-gray-600 ring-1 ring-gray-200 dark:ring-gray-700' : 'primary-200 dark:border-primary-500/20 ring-1 ring-primary-100 dark:ring-primary-500/10' }}'
                    : 'border-gray-100 dark:border-gray-800 hover:border-gray-200 dark:hover:border-gray-700 hover:shadow-sm'"
                wire:key="respondent-{{ $respondent['user_id'] }}-{{ $index }}"
            >
                {{-- Card header --}}
                <button
                    type="button"
                    @click="open = !open"
                    class="w-full flex items-center gap-3 px-4 py-3.5 text-left group"
                    :aria-expanded="open"
                >
                    {{-- Avatar --}}
                    @if ($respondent['avatar'])
                        <img src="{{ $respondent['avatar'] }}" alt="" class="w-10 h-10 rounded-xl object-cover shrink-0 shadow-sm" />
                    @else
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl shadow-sm {{ $p['solid'] }}">
                            <span class="select-none text-sm font-black text-white">{{ $initial }}</span>
                        </div>
                    @endif

                    {{-- Identity --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center flex-wrap gap-1.5 mb-0.5">
                            <span class="text-sm font-bold text-gray-800 dark:text-gray-100 truncate">
                                {{ $respondent['display_name'] }}
                            </span>
                            @if ($respondent['is_new'])
                                <span class="inline-flex items-center gap-1 text-[9px] font-bold uppercase px-1.5 py-0.5 rounded-full bg-rose-500 text-white">
                                    <span class="w-1 h-1 rounded-full bg-white/80 animate-pulse"></span>Nouveau
                                </span>
                            @endif
                            @if (!empty($respondent['respondent_kind_label']))
                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded-full {{ $p['badge'] }} border">
                                    {{ $respondent['respondent_kind_label'] }}
                                </span>
                            @endif
                            @if ($respondent['is_anonymous'])
                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded-full bg-gray-200/80 dark:bg-gray-700/80 text-gray-500 dark:text-gray-400">
                                    Anonyme
                                </span>
                            @endif
                        </div>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 truncate">{{ $respondent['email'] }}</p>
                    </div>

                    {{-- Completion badge + date --}}
                    <div class="shrink-0 flex flex-col items-end gap-1">
                        {{-- Completion pill --}}
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full
                            {{ $completePct === 100
                                ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-500/20'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400' }}">
                            @if ($completePct === 100)
                                <x-filament::icon icon="heroicon-s-check-circle" class="w-3 h-3" />
                            @endif
                            {{ $completePct }}%
                        </span>
                        {{-- Date --}}
                        <span class="text-[10px] text-gray-400 tabular-nums whitespace-nowrap">{{ $respondent['submitted_at'] }}</span>
                    </div>

                    {{-- Expand chevron --}}
                    <span
                        class="ml-1 shrink-0 transition-transform duration-200 text-gray-300 dark:text-gray-700 group-hover:text-gray-500"
                        :class="{ 'rotate-180 !text-primary-500': open }"
                    >
                        <x-filament::icon icon="heroicon-s-chevron-down" class="w-4 h-4" />
                    </span>
                </button>

                {{-- Expanded answers --}}
                <div x-show="open" x-collapse>
                    @include('filament.surveys.partials.respondent-answers', ['respondent' => $respondent, 'palette' => $p])
                </div>
            </div>
        @endforeach

        {{-- ═══ Footer ════════════════════════════════════════════─────────────────── --}}
        <p class="mt-4 text-center text-[11px] text-gray-300 dark:text-gray-700 select-none tabular-nums">
            {{ $total }} participant{{ $total > 1 ? 's' : '' }} au total
        </p>
    </div>
@endif
