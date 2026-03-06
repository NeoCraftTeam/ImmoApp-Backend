@php
    /** @var \Filament\Infolists\Components\ViewEntry $entry */
    $livewire = $entry->getLivewire();
    $respondents = $livewire->getRespondentsWithAnswers();
    $total = $respondents->count();

    // Palette of accent colors cycling per respondent
    $palettes = [
        ['ring' => 'ring-violet-400', 'from' => 'from-violet-500', 'to' => 'to-purple-600',  'badge' => 'bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-300 border-violet-200 dark:border-violet-500/20', 'num' => 'from-violet-500 to-purple-600'],
        ['ring' => 'ring-sky-400',    'from' => 'from-sky-500',    'to' => 'to-blue-600',    'badge' => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-500/20',         'num' => 'from-sky-500 to-blue-600'],
        ['ring' => 'ring-emerald-400','from' => 'from-emerald-500','to' => 'to-teal-600',    'badge' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/20', 'num' => 'from-emerald-500 to-teal-600'],
        ['ring' => 'ring-rose-400',   'from' => 'from-rose-500',   'to' => 'to-pink-600',    'badge' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/20',   'num' => 'from-rose-500 to-pink-600'],
        ['ring' => 'ring-amber-400',  'from' => 'from-amber-500',  'to' => 'to-orange-600',  'badge' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/20', 'num' => 'from-amber-500 to-orange-600'],
        ['ring' => 'ring-indigo-400', 'from' => 'from-indigo-500', 'to' => 'to-blue-700',    'badge' => 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-500/20', 'num' => 'from-indigo-500 to-blue-700'],
        ['ring' => 'ring-fuchsia-400','from' => 'from-fuchsia-500','to' => 'to-purple-700',  'badge' => 'bg-fuchsia-50 dark:bg-fuchsia-500/10 text-fuchsia-700 dark:text-fuchsia-300 border-fuchsia-200 dark:border-fuchsia-500/20','num' => 'from-fuchsia-500 to-purple-700'],
        ['ring' => 'ring-cyan-400',   'from' => 'from-cyan-500',   'to' => 'to-sky-600',     'badge' => 'bg-cyan-50 dark:bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border-cyan-200 dark:border-cyan-500/20',     'num' => 'from-cyan-500 to-sky-600'],
    ];

    // Compute aggregate stats
    $totalAnswers = $respondents->sum('answer_count');
    $allRatings = $respondents->flatMap(fn ($r) => collect($r['answers'])
        ->filter(fn ($a) => $a['type'] === 'rating' && $a['has_answer'])
        ->map(fn ($a) => (int) preg_replace('/\D.*/', '', $a['answer']))
    );
    $avgRating = $allRatings->count() ? round($allRatings->avg(), 1) : null;
@endphp

@if ($respondents->isEmpty())
    {{-- ─── Empty state ──────────────────────────────────────────────────── --}}
    <div class="flex flex-col items-center justify-center py-24 text-center select-none">
        <div class="relative mb-8 mx-auto w-24 h-24">
            <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-700 flex items-center justify-center shadow-xl shadow-black/[.06]">
                <x-filament::icon icon="heroicon-o-inbox" class="w-11 h-11 text-gray-400 dark:text-gray-500" />
            </div>
            <span class="absolute -bottom-2 -right-2 w-9 h-9 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center justify-center shadow-md">
                <x-filament::icon icon="heroicon-s-clock" class="w-4 h-4 text-gray-400" />
            </span>
        </div>
        <p class="text-base font-bold text-gray-800 dark:text-gray-200 mb-2">Aucune réponse pour l'instant</p>
        <p class="text-sm text-gray-400 dark:text-gray-500 max-w-xs leading-relaxed">Les réponses de vos clients apparaîtront ici dès que le sondage sera complété.</p>
    </div>

@else
    {{-- ─── Stats strip ──────────────────────────────────────────────────── --}}
    <div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4">

        {{-- Respondents --}}
        <div class="rounded-2xl p-4 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-sm flex items-center gap-3">
            <span class="w-10 h-10 shrink-0 rounded-xl bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center">
                <x-filament::icon icon="heroicon-s-users" class="w-5 h-5 text-primary-500" />
            </span>
            <div>
                <p class="text-2xl font-black text-gray-900 dark:text-gray-50 leading-none tabular-nums">{{ $total }}</p>
                <p class="mt-0.5 text-[11px] font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide">{{ $total > 1 ? 'Répondants' : 'Répondant' }}</p>
            </div>
        </div>

        {{-- Total answers --}}
        <div class="rounded-2xl p-4 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-sm flex items-center gap-3">
            <span class="w-10 h-10 shrink-0 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center">
                <x-filament::icon icon="heroicon-s-chat-bubble-bottom-center-text" class="w-5 h-5 text-emerald-500" />
            </span>
            <div>
                <p class="text-2xl font-black text-gray-900 dark:text-gray-50 leading-none tabular-nums">{{ $totalAnswers }}</p>
                <p class="mt-0.5 text-[11px] font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide">Réponses</p>
            </div>
        </div>

        {{-- Average rating --}}
        <div class="rounded-2xl p-4 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-sm flex items-center gap-3">
            <span class="w-10 h-10 shrink-0 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center">
                <x-filament::icon icon="heroicon-s-star" class="w-5 h-5 text-amber-400" />
            </span>
            <div>
                <p class="text-2xl font-black text-gray-900 dark:text-gray-50 leading-none tabular-nums">
                    {{ $avgRating !== null ? $avgRating : '—' }}<span class="text-sm font-semibold text-gray-400 dark:text-gray-500">{{ $avgRating !== null ? '/5' : '' }}</span>
                </p>
                <p class="mt-0.5 text-[11px] font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide">Note moy.</p>
            </div>
        </div>

        {{-- Questions answered --}}
        <div class="col-span-2 sm:col-span-1 rounded-2xl p-4 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-sm flex items-center gap-3">
            <span class="w-10 h-10 shrink-0 rounded-xl bg-violet-50 dark:bg-violet-500/10 flex items-center justify-center">
                <x-filament::icon icon="heroicon-s-clipboard-document-check" class="w-5 h-5 text-violet-500" />
            </span>
            <div>
                <p class="text-2xl font-black text-gray-900 dark:text-gray-50 leading-none tabular-nums">
                    {{ $total > 0 ? round($totalAnswers / $total, 1) : '—' }}
                    <span class="text-sm font-semibold text-gray-400 dark:text-gray-500">rép./pers.</span>
                </p>
                <p class="mt-0.5 text-[11px] font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide">Moy. réponses</p>
            </div>
        </div>
    </div>

    {{-- ─── Respondent cards ─────────────────────────────────────────────── --}}
    <div class="space-y-2">
        @foreach ($respondents as $index => $respondent)
            @php
                $p = $palettes[$index % count($palettes)];
                $initial = Str::upper(Str::substr($respondent['display_name'], 0, 1));
            @endphp

            <div
                x-data="{ open: false }"
                class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden shadow-sm transition-shadow duration-200"
                :class="open ? 'shadow-md' : 'hover:shadow-md'"
            >
                {{-- ── Header row (clickable) ─────────────────────────── --}}
                <button
                    type="button"
                    @click="open = !open"
                    class="w-full flex items-center gap-4 px-5 py-4 text-left group"
                    :aria-expanded="open"
                >
                    {{-- Avatar --}}
                    <div class="relative shrink-0">
                        @if ($respondent['avatar'])
                            <img
                                src="{{ $respondent['avatar'] }}"
                                alt="{{ $respondent['display_name'] }}"
                                class="w-11 h-11 rounded-xl object-cover ring-2 ring-white dark:ring-gray-900 shadow"
                            />
                        @else
                            <div class="w-11 h-11 rounded-xl bg-gradient-to-br {{ $p['from'] }} {{ $p['to'] }} flex items-center justify-center shadow ring-2 ring-white dark:ring-gray-900">
                                <span class="text-base font-black text-white select-none">{{ $initial }}</span>
                            </div>
                        @endif
                        {{-- Online dot --}}
                        <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-white dark:bg-gray-900 flex items-center justify-center">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-sm shadow-emerald-400/60"></span>
                        </span>
                    </div>

                    {{-- Name / email --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $respondent['display_name'] }}</span>
                            @if (! $respondent['user'])
                                <span class="shrink-0 text-[9px] font-bold uppercase tracking-widest px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500">
                                    Anonyme
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 truncate mt-0.5">{{ $respondent['email'] }}</p>
                    </div>

                    {{-- Right meta --}}
                    <div class="hidden sm:flex items-center gap-2 shrink-0">
                        <span class="text-xs text-gray-400 dark:text-gray-500 tabular-nums whitespace-nowrap">
                            {{ $respondent['submitted_at'] }}
                        </span>
                    </div>

                    {{-- Chevron --}}
                    <span
                        class="ml-1 shrink-0 transition-transform duration-200 text-gray-400 dark:text-gray-600 group-hover:text-gray-600 dark:group-hover:text-gray-400"
                        :class="{ 'rotate-180 !text-primary-500': open }"
                    >
                        <x-filament::icon icon="heroicon-s-chevron-down" class="w-4 h-4" />
                    </span>
                </button>

                {{-- ── Answers panel ──────────────────────────────────── --}}
                <div x-show="open" x-collapse>

                    {{-- Mobile meta strip --}}
                    <div class="sm:hidden flex items-center justify-end px-5 pb-3">
                        <span class="text-xs text-gray-400 dark:text-gray-500 tabular-nums">{{ $respondent['submitted_at'] }}</span>
                    </div>

                    <div class="mx-5 border-t border-dashed border-gray-100 dark:border-gray-800"></div>

                    <div class="px-5 py-4 space-y-1">
                        @foreach ($respondent['answers'] as $i => $qa)
                            <div class="flex gap-4 py-3.5 {{ ! $loop->last ? 'border-b border-gray-50 dark:border-gray-800/60' : '' }}">

                                {{-- Step number --}}
                                <div class="shrink-0 mt-0.5 w-6 h-6 rounded-lg bg-gradient-to-br {{ $p['from'] }} {{ $p['to'] }} flex items-center justify-center shadow-sm">
                                    <span class="text-[9px] font-black text-white tabular-nums leading-none">{{ $i + 1 }}</span>
                                </div>

                                {{-- Q&A body --}}
                                <div class="flex-1 min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2 leading-none">
                                        {{ $qa['question'] }}
                                    </p>

                                    @if ($qa['has_answer'])
                                        @if ($qa['type'] === 'rating')
                                            @php
                                                preg_match('/(\d+)/', $qa['answer'], $m);
                                                $stars = (int) ($m[1] ?? 0);
                                            @endphp
                                            <div class="flex items-center gap-0.5">
                                                @for ($s = 1; $s <= 5; $s++)
                                                    @if ($s <= $stars)
                                                        <x-filament::icon icon="heroicon-s-star" class="w-5 h-5 text-amber-400 drop-shadow-sm" />
                                                    @else
                                                        <x-filament::icon icon="heroicon-o-star" class="w-5 h-5 text-gray-200 dark:text-gray-700" />
                                                    @endif
                                                @endfor
                                                <span class="ml-2 text-sm font-bold text-amber-500 dark:text-amber-400 tabular-nums">{{ $stars }}/5</span>
                                            </div>

                                        @elseif ($qa['type'] === 'checkbox' || $qa['type'] === 'multiple_choice')
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach (explode(', ', $qa['answer']) as $chip)
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold border {{ $p['badge'] }}">
                                                        {{ trim($chip) }}
                                                    </span>
                                                @endforeach
                                            </div>

                                        @else
                                            <p class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">{{ $qa['answer'] }}</p>
                                        @endif

                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs italic text-gray-350 dark:text-gray-600">
                                            <x-filament::icon icon="heroicon-o-minus-circle" class="w-3.5 h-3.5" />
                                            Sans réponse
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>
        @endforeach
    </div>

    {{-- ─── Footer note ──────────────────────────────────────────────────── --}}
    <p class="mt-4 text-center text-xs text-gray-300 dark:text-gray-700 select-none">
        {{ $total }} {{ $total > 1 ? 'participants' : 'participant' }} · {{ $totalAnswers }} réponses au total
    </p>
@endif

