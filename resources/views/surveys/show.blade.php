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
        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" /></svg>
        <span><strong>Ce sondage est 100 % anonyme</strong> — aucune information personnelle n'est collectée.</span>
    </div>

    {{-- Already submitted --}}
    @if ($alreadySubmitted)
        <div class="rounded-2xl border border-amber-200 dark:border-amber-700/40 bg-amber-50 dark:bg-amber-900/20 p-8 text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-50 mb-2">Vous avez déjà répondu à ce sondage</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Merci pour votre participation !</p>
            <a href="{{ route('surveys.index') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                Voir les autres sondages
            </a>
        </div>
    @else
        {{-- Survey header --}}
        <div class="mb-8">
            <h1 class="text-2xl font-black text-gray-900 dark:text-gray-50 mb-2">{{ $survey->title }}</h1>
            @if ($survey->description)
                <p class="text-gray-500 dark:text-gray-400 leading-relaxed">{{ $survey->description }}</p>
            @endif
        </div>

        {{-- Wizard form --}}
        <div
            x-data="{
                step: 0,
                total: {{ $survey->questions->count() }},
                answers: {},
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
                    return Math.round((this.step / this.total) * 100);
                },
            }"
        >
            {{-- Progress bar --}}
            <div class="mb-6">
                <div class="flex justify-between text-xs font-semibold text-gray-400 dark:text-gray-500 mb-2">
                    <span>Question <span x-text="step + 1"></span> sur {{ $survey->questions->count() }}</span>
                    <span x-text="progressPct() + ' %'"></span>
                </div>
                <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                    <div
                        class="h-full bg-primary-500 rounded-full transition-all duration-500"
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
                        x-show="step === {{ $i }}"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-x-4"
                        x-transition:enter-end="opacity-100 translate-x-0"
                        class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm"
                    >
                        <p class="text-[11px] font-bold uppercase tracking-widest text-primary-500 mb-3">Question {{ $i + 1 }}</p>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-50 mb-6 leading-snug">{{ $question->text }}</h2>

                        @if ($question->type === 'multiple_choice')
                            <div class="space-y-3">
                                @foreach ($question->options ?? [] as $option)
                                    <label class="flex items-center gap-3 p-3.5 rounded-xl border border-gray-100 dark:border-gray-700 cursor-pointer hover:border-primary-300 dark:hover:border-primary-600 transition-colors"
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
                            @php $answers[$question->id] = []; @endphp
                            <div class="space-y-3" x-init="answers['{{ $question->id }}'] = answers['{{ $question->id }}'] ?? []">
                                @foreach ($question->options ?? [] as $option)
                                    <label class="flex items-center gap-3 p-3.5 rounded-xl border border-gray-100 dark:border-gray-700 cursor-pointer hover:border-primary-300 dark:hover:border-primary-600 transition-colors"
                                        :class="(answers['{{ $question->id }}'] || []).includes('{{ $option }}') ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : ''"
                                    >
                                        <input type="checkbox" class="sr-only"
                                            value="{{ $option }}"
                                            x-model="answers['{{ $question->id }}']"
                                        >
                                        <span class="w-5 h-5 shrink-0 rounded-md border-2 flex items-center justify-center transition-colors"
                                            :class="(answers['{{ $question->id }}'] || []).includes('{{ $option }}') ? 'border-primary-500 bg-primary-500' : 'border-gray-300 dark:border-gray-600'"
                                        >
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                x-show="(answers['{{ $question->id }}'] || []).includes('{{ $option }}')"
                                            ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        </span>
                                        <span class="text-sm font-medium">{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>

                        @elseif ($question->type === 'rating')
                            <div
                                class="flex gap-2"
                                x-data="{ hovered: 0 }"
                                x-init="answers['{{ $question->id }}'] = answers['{{ $question->id }}'] ?? ''"
                            >
                                @for ($star = 1; $star <= 5; $star++)
                                    <button
                                        type="button"
                                        @click="answers['{{ $question->id }}'] = '{{ $star }}'"
                                        @mouseenter="hovered = {{ $star }}"
                                        @mouseleave="hovered = 0"
                                        class="group relative w-12 h-12 rounded-xl flex items-center justify-center transition-transform hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                        :class="(hovered >= {{ $star }} || answers['{{ $question->id }}'] >= {{ $star }}) ? 'text-amber-400' : 'text-gray-200 dark:text-gray-700'"
                                        :aria-label="'{{ $star }} étoile{{ $star > 1 ? 's' : '' }}'"
                                    >
                                        <svg class="w-9 h-9 drop-shadow-sm" fill="currentColor" viewBox="0 0 24 24">
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
                                class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-transparent px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none transition"
                                rows="4"
                                maxlength="1000"
                                placeholder="Votre réponse…"
                                x-model="answers['{{ $question->id }}']"
                                x-init="answers['{{ $question->id }}'] = answers['{{ $question->id }}'] ?? ''"
                                @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                            ></textarea>
                            <p class="mt-1 text-right text-xs text-gray-400"
                                x-text="(answers['{{ $question->id }}'] || '').length + ' / 1000'"
                            ></p>
                        @endif
                    </div>
                @endforeach

                {{-- Validation errors --}}
                @if ($errors->any())
                    <div class="mt-4 rounded-xl border border-red-200 dark:border-red-700/40 bg-red-50 dark:bg-red-900/20 p-4 text-sm text-red-600 dark:text-red-400">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Navigation --}}
                <div class="mt-6 flex justify-between gap-3">
                    <button
                        type="button"
                        x-show="step > 0"
                        @click="step--"
                        class="px-5 py-2.5 rounded-xl text-sm font-semibold border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors"
                    >
                        ← Précédent
                    </button>
                    <div class="flex-1"></div>

                    {{-- Next --}}
                    <button
                        type="button"
                        x-show="step < total - 1"
                        @click="isCurrentAnswered() && step++"
                        :disabled="!isCurrentAnswered()"
                        class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all"
                    >
                        Suivant →
                    </button>

                    {{-- Submit --}}
                    <button
                        type="submit"
                        x-show="step === total - 1"
                        :disabled="!isCurrentAnswered()"
                        class="relative px-8 py-2.5 rounded-xl text-sm font-bold bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-lg shadow-primary-500/30"
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
        <div class="mt-8 pt-6 border-t border-gray-100 dark:border-gray-800">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">Partager ce sondage</p>
            @include('surveys._share', ['survey' => $survey])
        </div>
    @endif
</x-surveys-layout>
