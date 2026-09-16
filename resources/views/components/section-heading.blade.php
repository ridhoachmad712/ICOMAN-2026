@props(['title', 'subtitle' => null, 'eyebrow' => null, 'center' => true, 'section' => null])

@php
    // Dalam mode sunting, teks judul bisa diketik langsung di halaman dan
    // disimpan saat kursor meninggalkannya. HTML tempelan dibersihkan di sisi
    // server, jadi contenteditable biasa sudah cukup.
    $editable = $section && pageEditMode();

    $editAttributes = function (string $field) use ($editable, $section): string {
        if (! $editable) {
            return '';
        }

        return 'contenteditable="true" data-editable="'.$field.'"'
            .' x-on:blur="Livewire.dispatch(\'ps-text\', { id: '.$section->id.', field: \''.$field.'\', value: $el.innerText })"';
    };
@endphp

<div data-reveal class="{{ $center ? 'text-center mx-auto' : '' }} mb-12 max-w-2xl">
    @if($eyebrow)
        <p {!! $editAttributes('eyebrow') !!} class="eyebrow {{ $center ? 'justify-center' : '' }} mb-3">{{ $eyebrow }}</p>
    @endif
    <h2 {!! $editAttributes('heading') !!} class="font-display text-3xl font-bold tracking-tight text-[var(--brand-2)] sm:text-4xl">{{ $title }}</h2>
    @if($subtitle)
        <p {!! $editAttributes('subheading') !!} class="mt-4 text-base leading-relaxed text-slate-500">{{ $subtitle }}</p>
    @endif
    @unless($eyebrow)
        {{-- Garis penanda, bukan hiasan: satu warna merek yang tegas, bukan
             peralihan dua warna yang meleburkannya. --}}
        <div class="mt-5 h-1 w-16 rounded-full bg-[var(--brand)] {{ $center ? 'mx-auto' : '' }}"></div>
    @endunless
</div>
