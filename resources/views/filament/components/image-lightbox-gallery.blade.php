<div
    x-data="{
        open: false,
        currentIndex: 0,
        lastOpenedIndex: 0,
        images: [],
        init() {
            this.images = Array.from(this.$refs.gallery.querySelectorAll('img')).map(img => img.src);
        },
        openLightbox(index) {
            this.lastOpenedIndex = index;
            this.currentIndex = index;
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.lightboxClose?.focus());
        },
        closeLightbox() {
            this.open = false;
            document.body.style.overflow = '';
            this.$nextTick(() => {
                const buttons = this.$refs.gallery?.querySelectorAll('button[type=\'button\']');
                buttons?.[this.lastOpenedIndex]?.focus();
            });
        },
        next() {
            this.currentIndex = (this.currentIndex + 1) % this.images.length;
        },
        prev() {
            this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
        },
    }"
    x-on:keydown.escape.window="closeLightbox()"
    x-on:keydown.arrow-right.window="if (open) next()"
    x-on:keydown.arrow-left.window="if (open) prev()"
>
    @php
        $record = $getRecord();
        $media = $record->getMedia('images');
        $lightboxTitleId = 'ad-gallery-lightbox-title-'.($record->id ?? '0');
    @endphp

    @if($media->count() > 0)
        <div x-ref="gallery" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            @foreach($media as $index => $item)
                <button
                    type="button"
                    x-on:click="openLightbox({{ $index }})"
                    aria-label="Afficher la photo {{ $index + 1 }} en grand"
                    class="group relative aspect-square overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all hover:ring-2 hover:ring-primary-400"
                >
                    <img
                        src="{{ $item->getUrl() }}"
                        alt=""
                        class="h-full w-full object-cover transition-[transform] duration-300 ease-[cubic-bezier(0.25,1,0.5,1)] motion-reduce:transition-none group-hover:scale-105 motion-reduce:group-hover:scale-100"
                        loading="lazy"
                    />
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors duration-300 flex items-center justify-center">
                        <x-heroicon-o-arrows-pointing-out class="h-6 w-6 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300 drop-shadow-lg" />
                    </div>
                </button>
            @endforeach
        </div>

        {{-- Lightbox overlay --}}
        <template x-teleport="body">
            <div
                x-show="open"
                x-transition:enter="transition ease-[cubic-bezier(0.25,1,0.5,1)] duration-200 motion-reduce:transition-none"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                role="dialog"
                aria-modal="true"
                aria-labelledby="{{ $lightboxTitleId }}"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/90 backdrop-blur-sm motion-reduce:backdrop-blur-none"
                x-on:click.self="closeLightbox()"
                style="display: none;"
            >
                <h2 id="{{ $lightboxTitleId }}" class="sr-only">Galerie photos</h2>

                {{-- Close button --}}
                <button
                    type="button"
                    x-ref="lightboxClose"
                    x-on:click="closeLightbox()"
                    aria-label="Fermer la galerie"
                    class="absolute top-4 right-4 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors duration-200 ease-[cubic-bezier(0.25,1,0.5,1)] motion-reduce:transition-none"
                >
                    <x-heroicon-o-x-mark class="h-6 w-6" />
                </button>

                {{-- Counter --}}
                <div
                    class="absolute top-4 left-4 z-10 rounded-full bg-white/10 px-3 py-1.5 text-sm font-medium text-white"
                    aria-live="polite"
                    aria-atomic="true"
                >
                    <span x-text="currentIndex + 1"></span> / <span x-text="images.length"></span>
                </div>

                {{-- Previous button --}}
                <button
                    type="button"
                    x-on:click="prev()"
                    x-show="images.length > 1"
                    aria-label="Photo précédente"
                    class="absolute left-4 z-10 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors duration-200 ease-[cubic-bezier(0.25,1,0.5,1)] motion-reduce:transition-none"
                >
                    <x-heroicon-o-chevron-left class="h-7 w-7" />
                </button>

                {{-- Image --}}
                <img
                    x-bind:src="images[currentIndex]"
                    x-bind:alt="'Photo ' + (currentIndex + 1) + ' sur ' + images.length"
                    class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
                />

                {{-- Next button --}}
                <button
                    type="button"
                    x-on:click="next()"
                    x-show="images.length > 1"
                    aria-label="Photo suivante"
                    class="absolute right-4 z-10 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors duration-200 ease-[cubic-bezier(0.25,1,0.5,1)] motion-reduce:transition-none"
                >
                    <x-heroicon-o-chevron-right class="h-7 w-7" />
                </button>
            </div>
        </template>
    @else
        <p class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">Aucune photo disponible</p>
    @endif
</div>
