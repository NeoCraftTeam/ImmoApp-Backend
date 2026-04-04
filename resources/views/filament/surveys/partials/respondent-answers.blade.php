@php
    /** @var array $respondent */
    /** @var array $palette */
    $p = $palette;
    $answeredCount = collect($respondent['answers'])->where('has_answer', true)->count();
    $totalCount    = count($respondent['answers']);
    $completionPct = $totalCount > 0 ? round(($answeredCount / $totalCount) * 100) : 0;
@endphp

{{-- ─ Divider ────────────────────────────────────────────────────────────── --}}
<div class="mx-4 h-px bg-gradient-to-r from-transparent via-gray-200 dark:via-gray-700 to-transparent"></div>

{{-- ─ Answer list ──────────────────────────────────────────────────────────── --}}
<div class="p-4 space-y-3">
    @foreach ($respondent['answers'] as $i => $qa)
        <div class="rounded-xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900/60 overflow-hidden">

            {{-- Question header --}}
            <div class="flex items-start gap-3 px-3.5 pt-3 pb-2">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white shadow-sm {{ $p['solid'] }}">{{ $i + 1 }}</span>
                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 leading-snug flex-1">{{ $qa['question'] }}</p>
                @if (!$qa['has_answer'])
                    <span class="shrink-0 text-[9px] font-semibold uppercase tracking-wide text-gray-300 dark:text-gray-600">—</span>
                @endif
            </div>

            {{-- Answer body --}}
            @if ($qa['has_answer'])
                <div class="px-3.5 pb-3 pl-[3.25rem]">

                    @if ($qa['type'] === 'rating')
                        @php
                            preg_match('/(\d+)/', $qa['answer'], $m);
                            $stars = (int) ($m[1] ?? 0);
                            $starPct = $stars * 20;
                        @endphp
                        <div class="flex items-center gap-3">
                            {{-- Stars --}}
                            <div class="flex gap-0.5">
                                @for ($s = 1; $s <= 5; $s++)
                                    @if ($s <= $stars)
                                        <x-filament::icon icon="heroicon-s-star" class="w-5 h-5 text-amber-400" />
                                    @else
                                        <x-filament::icon icon="heroicon-o-star" class="w-5 h-5 text-gray-200 dark:text-gray-700" />
                                    @endif
                                @endfor
                            </div>
                            {{-- Score badge --}}
                            <span class="text-sm font-extrabold tabular-nums
                                {{ $stars >= 4 ? 'text-emerald-600 dark:text-emerald-400' : ($stars >= 3 ? 'text-amber-500' : 'text-rose-500') }}">
                                {{ $stars }}<span class="text-[10px] font-semibold text-gray-400">/5</span>
                            </span>
                            {{-- Progress bar --}}
                            <div class="flex-1 h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden max-w-[100px]">
                                <div class="h-full rounded-full transition-all duration-500
                                    {{ $stars >= 4 ? 'bg-emerald-400' : ($stars >= 3 ? 'bg-amber-400' : 'bg-rose-400') }}"
                                    style="width: {{ $starPct }}%">
                                </div>
                            </div>
                        </div>

                    @elseif ($qa['type'] === 'checkbox' || $qa['type'] === 'multiple_choice')
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (explode(', ', $qa['answer']) as $chip)
                                @if (trim($chip) !== '')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $p['badge'] }}">
                                        <span class="w-1 h-1 rounded-full {{ $p['solid'] }} opacity-60"></span>
                                        {{ trim($chip) }}
                                    </span>
                                @endif
                            @endforeach
                        </div>

                    @elseif ($qa['type'] === 'boolean' || $qa['type'] === 'yes_no')
                        @php $isYes = in_array(strtolower($qa['answer']), ['oui', 'yes', '1', 'true']); @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold
                            {{ $isYes ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/20' : 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/20' }}">
                            @if ($isYes)
                                <x-filament::icon icon="heroicon-s-check-circle" class="w-3.5 h-3.5" />
                            @else
                                <x-filament::icon icon="heroicon-s-x-circle" class="w-3.5 h-3.5" />
                            @endif
                            {{ $qa['answer'] }}
                        </span>

                    @else
                        {{-- Text / paragraph answer — quote style --}}
                        <div class="border-l-[3px] {{ $p['solid'] }} opacity-100 pl-3 rounded-r">
                            <p class="text-[13px] text-gray-700 dark:text-gray-300 leading-relaxed">{{ $qa['answer'] }}</p>
                        </div>
                    @endif

                </div>
            @else
                {{-- No answer --}}
                <div class="pl-[3.25rem] pb-3 flex items-center gap-1.5 text-gray-300 dark:text-gray-600">
                    <x-filament::icon icon="heroicon-o-minus-circle" class="w-4 h-4 shrink-0" />
                    <span class="text-[11px] italic">Sans réponse</span>
                </div>
            @endif
        </div>
    @endforeach
</div>

{{-- ─ Footer: completion + date ─────────────────────────────────────────── --}}
<div class="mx-4 mb-4 flex items-center justify-between rounded-xl bg-gray-50 dark:bg-gray-800/50 px-3.5 py-2.5 border border-gray-100 dark:border-gray-800">
    <div class="flex items-center gap-2">
        {{-- Mini completion bar --}}
        <div class="w-24 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
            <div class="h-full {{ $p['solid'] }} rounded-full" style="width: {{ $completionPct }}%"></div>
        </div>
        <span class="text-[11px] font-semibold
            {{ $completionPct === 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400' }}">
            {{ $answeredCount }}/{{ $totalCount }} répondué{{ $answeredCount > 1 ? 's' : '' }}
            @if($completionPct === 100) <span class="ml-0.5">✓</span>@endif
        </span>
    </div>
    <span class="text-[10px] text-gray-400 tabular-nums">{{ $respondent['submitted_at'] }}</span>
</div>
