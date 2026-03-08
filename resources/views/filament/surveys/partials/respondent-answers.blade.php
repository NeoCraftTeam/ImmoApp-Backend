@php
    /** @var array $respondent */
    /** @var array $palette */
    $p = $palette;
@endphp

<div class="sm:hidden flex justify-end px-4 pb-1">
    <span class="text-[10px] text-gray-400 tabular-nums">{{ $respondent['submitted_at'] }}</span>
</div>

<div class="mx-4 border-t border-dashed border-gray-100 dark:border-gray-800"></div>

<div class="p-4 space-y-3">
    @foreach ($respondent['answers'] as $i => $qa)
        <div class="rounded-lg bg-gray-50/80 dark:bg-gray-800/40 p-3">
            <div class="flex items-start gap-2 mb-2">
                <span class="shrink-0 w-5 h-5 rounded bg-gradient-to-br {{ $p['from'] }} {{ $p['to'] }} flex items-center justify-center">
                    <span class="text-[8px] font-black text-white tabular-nums">{{ $i + 1 }}</span>
                </span>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 leading-tight">{{ $qa['question'] }}</p>
            </div>

            @if ($qa['has_answer'])
                @if ($qa['type'] === 'rating')
                    @php
                        preg_match('/(\d+)/', $qa['answer'], $m);
                        $stars = (int) ($m[1] ?? 0);
                    @endphp
                    <div class="flex items-center gap-0.5 pl-7">
                        @for ($s = 1; $s <= 5; $s++)
                            @if ($s <= $stars)
                                <x-filament::icon icon="heroicon-s-star" class="w-4 h-4 text-amber-400" />
                            @else
                                <x-filament::icon icon="heroicon-o-star" class="w-4 h-4 text-gray-200 dark:text-gray-700" />
                            @endif
                        @endfor
                        <span class="ml-1 text-xs font-bold text-amber-500 tabular-nums">{{ $stars }}/5</span>
                    </div>
                @elseif ($qa['type'] === 'checkbox' || $qa['type'] === 'multiple_choice')
                    <div class="flex flex-wrap gap-1 pl-7">
                        @foreach (explode(', ', $qa['answer']) as $chip)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold border {{ $p['badge'] }}">{{ trim($chip) }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed pl-7">{{ $qa['answer'] }}</p>
                @endif
            @else
                <p class="text-[10px] italic text-gray-300 dark:text-gray-600 pl-7">Sans réponse</p>
            @endif
        </div>
    @endforeach
</div>
