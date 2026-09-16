@props(['speakers'])

@php
    $count = $speakers->count();
    // Empat kartu per tampilan di desktop. Bila jumlahnya belum melebihi itu,
    // carousel tidak ada gunanya (tidak ada yang bisa digeser) — tampilkan grid.
    $needsCarousel = $count > 4;
@endphp

@if($count === 0)
    {{-- Tidak ada yang ditampilkan; pemanggil yang mengurus state "Segera Diumumkan". --}}
@elseif(! $needsCarousel)
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($speakers as $speaker)
            <x-card-speaker :speaker="$speaker" />
        @endforeach
    </div>
@else
    <div
        role="region"
        aria-roledescription="carousel"
        aria-label="{{ app()->getLocale() === 'id' ? 'Daftar pembicara' : 'Speaker list' }}"
        class="relative"
        x-data="{
            index: 0,
            pages: 1,
            paused: false,
            timer: null,

            init() {
                this.measure();
                window.addEventListener('resize', () => this.measure());

                // Hormati pengaturan sistem: jangan bergerak sendiri bila
                // pengguna meminta animasi dikurangi.
                if (! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    this.timer = setInterval(() => this.paused || this.next(), 5000);
                }
            },

            // Lebar bisa 0 bila carousel sedang tidak terlihat (mis. di dalam
            // elemen tersembunyi). Membagi dengan 0 membuat jumlah halaman jadi
            // tak hingga dan merusak titik penanda, jadi selalu dijaga.
            width() {
                return this.$refs.track.clientWidth || 0;
            },

            measure() {
                const width = this.width();
                if (width <= 0) {
                    this.pages = 1;
                    this.index = 0;

                    return;
                }

                this.pages = Math.max(1, Math.round(this.$refs.track.scrollWidth / width));
                this.sync();
            },

            sync() {
                const width = this.width();
                if (width <= 0) {
                    return;
                }

                this.index = Math.min(this.pages - 1, Math.round(this.$refs.track.scrollLeft / width));
            },

            go(target) {
                const width = this.width();
                if (width <= 0) {
                    return;
                }

                this.index = (target + this.pages) % this.pages; // dari akhir kembali ke awal
                this.$refs.track.scrollTo({ left: this.index * width, behavior: 'smooth' });
            },

            next() { this.go(this.index + 1); },
            prev() { this.go(this.index - 1); },
        }"
        x-on:mouseenter="paused = true"
        x-on:mouseleave="paused = false"
        x-on:focusin="paused = true"
        x-on:focusout="paused = false"
        x-on:touchstart="paused = true"
    >
        <div
            x-ref="track"
            x-on:scroll.debounce.120ms="sync()"
            class="flex snap-x snap-mandatory gap-6 overflow-x-auto scroll-smooth pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
            @foreach($speakers as $speaker)
                {{-- Lebar = (100% - total gap) / jumlah kartu terlihat. --}}
                <div class="snap-start shrink-0 basis-full sm:basis-[calc(50%-0.75rem)] lg:basis-[calc(25%-1.125rem)]">
                    <x-card-speaker :speaker="$speaker" />
                </div>
            @endforeach
        </div>

        {{-- Panah: tetap tersedia untuk yang tidak bisa swipe (mouse & keyboard). --}}
        <button type="button" x-on:click="prev()"
                aria-label="{{ app()->getLocale() === 'id' ? 'Pembicara sebelumnya' : 'Previous speakers' }}"
                class="absolute -left-3 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-[var(--brand-2)] shadow-lg ring-1 ring-slate-200 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand)] sm:flex lg:-left-5">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
        </button>
        <button type="button" x-on:click="next()"
                aria-label="{{ app()->getLocale() === 'id' ? 'Pembicara selanjutnya' : 'Next speakers' }}"
                class="absolute -right-3 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-[var(--brand-2)] shadow-lg ring-1 ring-slate-200 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand)] sm:flex lg:-right-5">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
        </button>

        {{-- Titik penanda halaman --}}
        <div class="mt-5 flex items-center justify-center gap-2" x-show="pages > 1">
            <template x-for="page in pages" :key="page">
                <button type="button" x-on:click="go(page - 1)"
                        :aria-label="'{{ app()->getLocale() === 'id' ? 'Ke halaman ' : 'Go to page ' }}' + page"
                        :aria-current="index === page - 1"
                        class="h-2 rounded-full transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand)]"
                        :class="index === page - 1 ? 'w-6 bg-[var(--brand-ink)]' : 'w-2 bg-slate-300 hover:bg-slate-400'"></button>
            </template>
        </div>
    </div>
@endif
