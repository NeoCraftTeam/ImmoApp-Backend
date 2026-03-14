<div
    x-data="{
        open: false,
        currentIndex: 0,
        images: [],
        init() {
            this.images = Array.from(this.$refs.gallery.querySelectorAll('img')).map(img => img.src);
        },
        openLightbox(index) {
            this.currentIndex = index;
            this.open = true;
            document.body.style.overflow = 'hidden';
        },
        closeLightbox() {
            this.open = false;
            document.body.style.overflow = '';
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
    @endphp

    @if($media->count() > 0)
        <div x-ref="gallery" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            @foreach($media as $index => $item)
                <button
                    type="button"
                    x-on:click="openLightbox({{ $index }})"
                    class="group relative aspect-square overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all hover:ring-2 hover:ring-primary-400"
                >
                    <img
                        src="{{ $item->getUrl() }}"
                        alt="Photo {{ $index + 1 }}"
                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
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
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/90 backdrop-blur-sm"
                x-on:click.self="closeLightbox()"
                style="display: none;"
            >
                {{-- Close button --}}
                <button
                    x-on:click="closeLightbox()"
                    class="absolute top-4 right-4 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors"
                >
                    <x-heroicon-o-x-mark class="h-6 w-6" />
                </button>

                {{-- Counter --}}
                <div class="absolute top-4 left-4 z-10 rounded-full bg-white/10 px-3 py-1.5 text-sm font-medium text-white">
                    <span x-text="currentIndex + 1"></span> / <span x-text="images.length"></span>
                </div>

                {{-- Previous button --}}
                <button
                    x-on:click="prev()"
                    x-show="images.length > 1"
                    class="absolute left-4 z-10 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors"
                >
                    <x-heroicon-o-chevron-left class="h-7 w-7" />
                </button>

                {{-- Image --}}
                <img
                    x-bind:src="images[currentIndex]"
                    alt=""
                    class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
                />

                {{-- Next button --}}
                <button
                    x-on:click="next()"
                    x-show="images.length > 1"
                    class="absolute right-4 z-10 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors"
                >
                    <x-heroicon-o-chevron-right class="h-7 w-7" />
                </button>
            </div>
        </template>
    @else
        <p class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">Aucune photo disponible</p>
    @endif
</div>
