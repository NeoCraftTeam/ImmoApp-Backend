<x-surveys-layout :title="$survey->title">
    @push('meta')
        <meta property="og:title" content="{{ $survey->title }} — Donnez votre avis !" />
        <meta property="og:description" content="{{ Str::limit($survey->description ?? 'Participez à notre sondage anonyme.', 160) }}" />
        <meta property="og:url" content="{{ route('surveys.show', $survey) }}" />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary_large_image" />
        <link rel="canonical" href="{{ route('surveys.show', $survey) }}" />
    @endpush

    {{-- Anonymity banner --}}
    <div class="mb-6 flex items-center gap-2.5 rounded-xl border border-emerald-200 dark:border-emerald-700/40 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400">
        <svg class="h-4 w-4 shrink-0" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" /></svg>
        <span><strong>Ce sondage est 100 % anonyme</strong> — aucune information personnelle n'est collectée.</span>
    </div>

    {{-- Already submitted --}}
    @if ($alreadySubmitted)
        <div class="rounded-2xl border border-amber-200 dark:border-amber-700/40 bg-amber-50 dark:bg-amber-900/20 p-8 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 dark:bg-amber-900/40">
                <svg class="h-7 w-7 text-amber-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-50 mb-2">Vous avez déjà répondu à ce sondage</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Merci pour votre participation !</p>
            <a
                href="{{ route('surveys.index') }}"
                class="mt-4 inline-flex min-h-11 items-center gap-1.5 rounded-lg text-sm font-semibold text-primary-600 underline-offset-4 transition-colors hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-amber-50 dark:text-primary-400 dark:hover:text-primary-300 dark:focus-visible:ring-offset-gray-900"
            >
                Voir les autres sondages
            </a>
        </div>
    @else
        {{-- Survey header --}}
        <div class="mb-8 min-w-0">
            <h1 class="mb-2 break-words text-2xl font-black text-gray-900 dark:text-gray-50">{{ $survey->title }}</h1>
            @if ($survey->description)
                <p class="break-words leading-relaxed text-gray-500 dark:text-gray-400">{{ $survey->description }}</p>
            @endif
        </div>

        {{-- Wizard form --}}
        <div
            x-data="{
                step: 0,
                total: {{ $survey->questions->count() }},
                answers: {},
                focusActivePanel() {
                    this.$nextTick(() => {
                        const el = document.getElementById('survey-panel-' + this.step);
                        if (el) {
                            el.focus({ preventScroll: true });
                        }
                    });
                },
                isCurrentAnswered() {
                    const q = this.currentQuestion();
                    if (!q) return false;
                    const val = this.answers[q.id];
                    if (Array.isArray(val)) return val.length > 0;
                    return val !== undefined && val !== '';
                },
                currentQuestion() {
                    return @js($survey->questions->map(fn($q) => ['id' => $q->id, 'type' => $q->type])->values())[this.step] ?? null;
                },
                progressPct() {
                    if (this.total < 1) return 0;
                    return Math.round((this.step / this.total) * 100);
                },
            }"
        >
            {{-- Progress bar --}}
            <div class="mb-6" role="region" aria-labelledby="survey-progress-label">
                <div id="survey-progress-label" class="mb-2 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-xs font-semibold text-gray-400 dark:text-gray-500">
                    <span class="min-w-0">Question <span x-text="step + 1"></span> sur {{ $survey->questions->count() }}</span>
                    <span class="tabular-nums" aria-hidden="true" x-text="progressPct() + ' %'"></span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                    <div
                        class="h-full rounded-full bg-primary-500 transition-[width] duration-300 ease-out motion-reduce:transition-none"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-valuenow="progressPct()"
                        :aria-valuetext="'Question ' + (step + 1) + ' sur ' + total + ' — ' + progressPct() + ' %'"
                        :style="'width: ' + progressPct() + '%'"
                    ></div>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('surveys.submit', $survey) }}"
                x-ref="form"
                @submit.prevent="$refs.form.submit()"
            >
                @csrf

                @foreach ($survey->questions as $i => $question)
                    {{-- Hidden inputs that always submit --}}
                    <input type="hidden" name="answers[{{ $i }}][question_id]" value="{{ $question->id }}">
                    <input
                        type="hidden"
                        name="answers[{{ $i }}][answer]"
                        :value="Array.isArray(answers['{{ $question->id }}'])
                            ? answers['{{ $question->id }}'].join(', ')
                            : (answers['{{ $question->id }}'] ?? '')"
                    >

                    {{-- Step card --}}
                    <div
                        id="survey-panel-{{ $i }}"
                        tabindex="-1"
                        x-show="step === {{ $i }}"
                        x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                        x-transition:enter-start="opacity-0 translate-x-3 motion-reduce:translate-x-0"
                        x-transition:enter-end="opacity-100 translate-x-0"
                        x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                        x-transition:leave-start="opacity-100 translate-x-0"
                        x-transition:leave-end="opacity-0 -translate-x-2 motion-reduce:translate-x-0"
                        class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:focus-visible:ring-offset-gray-950"
                    >
                        <p class="mb-3 text-[11px] font-bold uppercase tracking-widest text-primary-500">Question {{ $i + 1 }}</p>
                        <h2 class="mb-6 break-words text-lg font-bold leading-snug text-gray-900 dark:text-gray-50" id="survey-question-{{ $question->id }}">{{ $question->text }}</h2>

                        @if ($question->type === 'multiple_choice')
                            <div class="space-y-3">
                                @foreach ($question->options ?? [] as $option)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-100 p-3.5 transition-colors hover:border-primary-300 focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2 focus-within:ring-offset-white dark:border-gray-700 dark:hover:border-primary-600 dark:focus-within:ring-offset-gray-900"
                                        :class="answers['{{ $question->id }}'] === '{{ $option }}' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : ''"
                                    >
                                        <input type="radio" class="sr-only"
                                            value="{{ $option }}"
                                            x-model="answers['{{ $question->id }}']"
                                        >
                                        <span class="w-5 h-5 shrink-0 rounded-full border-2 flex items-center justify-center transition-colors"
                                            :class="answers['{{ $question->id }}'] === '{{ $option }}' ? 'border-primary-500 bg-primary-500' : 'border-gray-300 dark:border-gray-600'"
                                        >
                                            <span class="w-2 h-2 rounded-full bg-white" x-show="answers['{{ $question->id }}'] === '{{ $option }}'"></span>
                                        </span>
                                        <span class="text-sm font-medium">{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>

                        @elseif ($question->type === 'checkbox')
                            <div class="space-y-3" x-init="answers['{{ $question->id }}'] = answers['{{ $question->id }}'] ?? []">
                                @foreach ($question->options ?? [] as $option)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-100 p-3.5 transition-colors hover:border-primary-300 focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2 focus-within:ring-offset-white dark:border-gray-700 dark:hover:border-primary-600 dark:focus-within:ring-offset-gray-900"
                                        :class="(answers['{{ $question->id }}'] || []).includes('{{ $option }}') ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : ''"
                                    >
                                        <input type="checkbox" class="sr-only"
                                            value="{{ $option }}"
                                            x-model="answers['{{ $question->id }}']"
                                        >
                                        <span class="w-5 h-5 shrink-0 rounded-md border-2 flex items-center justify-center transition-colors"
                                            :class="(answers['{{ $question->id }}'] || []).includes('{{ $option }}') ? 'border-primary-500 bg-primary-500' : 'border-gray-300 dark:border-gray-600'"
                                        >
                                            <svg class="h-3 w-3 text-white" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                x-show="(answers['{{ $question->id }}'] || []).includes('{{ $option }}')"
                                            ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        </span>
                                        <span class="text-sm font-medium">{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>

                        @elseif ($question->type === 'rating')
                            <div
                                class="flex flex-wrap items-center gap-2"
                                x-data="{ hovered: 0 }"
                                x-init="answers['{{ $question->id }}'] = answers['{{ $question->id }}'] ?? ''"
                            >
                                @for ($star = 1; $star <= 5; $star++)
                                    <button
                                        type="button"
                                        @click="answers['{{ $question->id }}'] = '{{ $star }}'"
                                        @mouseenter="hovered = {{ $star }}"
                                        @mouseleave="hovered = 0"
                                        class="group relative flex h-12 w-12 shrink-0 items-center justify-center rounded-xl transition-transform hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white active:scale-95 motion-reduce:hover:scale-100 motion-reduce:active:scale-100 dark:focus-visible:ring-offset-gray-900"
                                        :class="(hovered >= {{ $star }} || answers['{{ $question->id }}'] >= {{ $star }}) ? 'text-amber-400' : 'text-gray-200 dark:text-gray-700'"
                                        :aria-label="'{{ $star }} étoile{{ $star > 1 ? 's' : '' }}'"
                                    >
                                        <svg class="h-9 w-9 drop-shadow-sm" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                    </button>
                                @endfor
                                <span class="ml-2 self-center text-sm font-bold text-amber-500 dark:text-amber-400 tabular-nums w-8"
                                    x-text="answers['{{ $question->id }}'] ? answers['{{ $question->id }}'] + '/5' : ''"
                                ></span>
                            </div>

                        @else
                            <textarea
                                id="survey-answer-{{ $question->id }}"
                                class="min-h-[6.5rem] w-full resize-none rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 transition placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder:text-gray-500"
                                rows="4"
                                maxlength="1000"
                                placeholder="Votre réponse…"
                                aria-labelledby="survey-question-{{ $question->id }}"
                                aria-describedby="survey-answer-count-{{ $question->id }}"
                                x-model="answers['{{ $question->id }}']"
                                x-init="answers['{{ $question->id }}'] = answers['{{ $question->id }}'] ?? ''"
                                @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                            ></textarea>
                            <p
                                id="survey-answer-count-{{ $question->id }}"
                                class="mt-1 text-right text-xs text-gray-400"
                                aria-live="polite"
                                x-text="(answers['{{ $question->id }}'] || '').length + ' / 1000'"
                            ></p>
                        @endif
                    </div>
                @endforeach

                {{-- Validation errors --}}
                @if ($errors->any())
                    <div
                        id="survey-form-errors"
                        class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-600 dark:border-red-700/40 dark:bg-red-900/20 dark:text-red-400"
                        role="alert"
                        aria-live="assertive"
                        tabindex="-1"
                    >
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <script>
                        document.getElementById('survey-form-errors')?.focus({ preventScroll: true });
                    </script>
                @endif

                {{-- Navigation --}}
                <div class="mt-6 flex min-h-11 flex-wrap items-center justify-between gap-3">
                    <button
                        type="button"
                        x-show="step > 0"
                        @click="if (step > 0) { step--; focusActivePanel(); }"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-200 px-5 text-sm font-semibold transition-colors hover:border-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-50 active:bg-gray-100 dark:border-gray-700 dark:hover:border-gray-600 dark:hover:bg-gray-800/50 dark:focus-visible:ring-offset-gray-950 dark:active:bg-gray-800"
                    >
                        ← Précédent
                    </button>
                    <div class="min-w-2 flex-1"></div>

                    {{-- Next --}}
                    <button
                        type="button"
                        x-show="step < total - 1"
                        @click="if (isCurrentAnswered() && step < total - 1) { step++; focusActivePanel(); }"
                        :disabled="!isCurrentAnswered()"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-primary-600 px-6 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-50 active:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-40 disabled:active:bg-primary-600 dark:focus-visible:ring-offset-gray-950"
                    >
                        Suivant →
                    </button>

                    {{-- Submit --}}
                    <button
                        type="submit"
                        x-show="step === total - 1"
                        :disabled="!isCurrentAnswered()"
                        class="relative inline-flex min-h-11 items-center justify-center rounded-xl bg-primary-600 px-8 text-sm font-bold text-white shadow-md shadow-primary-500/25 transition-colors hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-50 active:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-40 disabled:active:bg-primary-600 dark:focus-visible:ring-offset-gray-950"
                    >
                        <span>Soumettre ✓</span>
                    </button>
                </div>

                {{-- No-JS fallback note --}}
                <noscript>
                    <p class="mt-4 text-xs text-gray-400 text-center">JavaScript désactivé — toutes les questions sont affichées ci-dessus.</p>
                </noscript>
            </form>
        </div>
    @endif

    {{-- Share section --}}
    @if (! $alreadySubmitted)
        <div class="mt-10 border-t border-gray-100 pt-8 dark:border-gray-800">
            <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Partager ce sondage</p>
            @include('surveys._share', ['survey' => $survey])
        </div>
    @endif
</x-surveys-layout>
