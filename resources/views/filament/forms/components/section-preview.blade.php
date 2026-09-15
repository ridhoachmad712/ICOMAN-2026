@php
    $record = $getRecord();
    $url = $record?->publicUrl();
    // Ditandai waktu simpan terakhir supaya pratinjau ikut segar setiap kali
    // blok disimpan, bukan menampilkan versi lama dari cache peramban.
    $src = $url ? $url.'?preview='.(optional($record->updated_at)->timestamp ?? time()) : null;
@endphp

@if($src)
    <div
        x-data="{
            width: 'full',
            fullscreen: false,
            widths: { full: '100%', tablet: '820px', mobile: '390px' },
            reload() { this.$refs.frame.src = this.$refs.frame.src; },
        }"
        x-bind:class="fullscreen && 'fixed inset-0 z-50 bg-white p-4 dark:bg-gray-900'"
        class="space-y-3"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-1 rounded-lg bg-gray-100 p-1 text-xs dark:bg-white/5">
                @foreach(['full' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Ponsel'] as $key => $label)
                    <button type="button" x-on:click="width = '{{ $key }}'"
                            x-bind:class="width === '{{ $key }}' ? 'bg-white shadow-sm dark:bg-white/10' : 'text-gray-500'"
                            class="rounded-md px-3 py-1.5 font-medium">{{ $label }}</button>
                @endforeach
            </div>

            <div class="flex items-center gap-2 text-xs">
                <button type="button" x-on:click="reload()"
                        class="rounded-md border border-gray-300 px-3 py-1.5 font-medium dark:border-white/10">Muat ulang</button>
                <button type="button" x-on:click="fullscreen = ! fullscreen"
                        class="rounded-md border border-gray-300 px-3 py-1.5 font-medium dark:border-white/10"
                        x-text="fullscreen ? 'Perkecil' : 'Layar penuh'">Layar penuh</button>
                <a href="{{ $url }}" target="_blank" rel="noopener"
                   class="font-medium text-primary-600 hover:underline dark:text-primary-400">Tab baru →</a>
            </div>
        </div>

        <div class="flex justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-gray-950">
            <iframe
                x-ref="frame"
                src="{{ $src }}"
                title="Pratinjau halaman"
                x-bind:style="`width: ${widths[width]}`"
                x-bind:class="fullscreen ? 'h-[calc(100vh-7rem)]' : 'h-[75vh]'"
                class="bg-white transition-[width] duration-200"
            ></iframe>
        </div>

        <p class="text-xs text-gray-500">Simpan untuk melihat perubahan. Tekan Esc untuk keluar dari layar penuh.</p>

        <div x-show="fullscreen" x-on:keydown.escape.window="fullscreen = false"></div>
    </div>
@else
    <p class="text-sm text-gray-500">Pratinjau tersedia setelah blok disimpan.</p>
@endif
