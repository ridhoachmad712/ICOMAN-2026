@php
    $record = $getRecord();
    $url = $record?->publicUrl();
@endphp

<div class="space-y-3">
    @if($url)
        {{-- Ditandai waktu simpan terakhir supaya pratinjau ikut segar setiap
             kali blok disimpan, bukan menampilkan versi lama dari cache. --}}
        <iframe
            src="{{ $url }}?preview={{ optional($record->updated_at)->timestamp ?? time() }}"
            title="Pratinjau halaman"
            class="h-[36rem] w-full rounded-xl border border-gray-200 bg-white dark:border-white/10"
            loading="lazy"
        ></iframe>

        <div class="flex items-center justify-between text-xs text-gray-500">
            <span>Pratinjau menampilkan seluruh halaman. Simpan untuk melihat perubahan.</span>
            <a href="{{ $url }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                Buka di tab baru →
            </a>
        </div>
    @else
        <p class="text-sm text-gray-500">Pratinjau tersedia setelah blok disimpan.</p>
    @endif
</div>
