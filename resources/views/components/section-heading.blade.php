@props(['title', 'subtitle' => null, 'eyebrow' => null, 'center' => true, 'section' => null, 'dark' => false])

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

<div data-reveal class="{{ $center ? 'text-center mx-auto' : '' }} mb-8 max-w-2xl sm:mb-10">
    {{-- Penanda bagian, selalu hadir dan selalu sama. Lihat .section-mark. --}}
    <div class="section-mark {{ $center ? 'mx-auto' : '' }}"></div>
    @if($eyebrow)
        <p {!! $editAttributes('eyebrow') !!} class="eyebrow {{ $center ? 'justify-center' : '' }} mb-2">{{ $eyebrow }}</p>
    @endif
    <h2 {!! $editAttributes('heading') !!} class="font-display text-3xl font-bold tracking-tight sm:text-4xl {{ $dark ? 'text-white' : 'text-[var(--brand-2)]' }}">{{ $title }}</h2>
    @if($subtitle)
        <p {!! $editAttributes('subheading') !!} class="mt-3 text-base leading-relaxed {{ $dark ? 'text-white/75' : 'text-slate-600' }}">{{ $subtitle }}</p>
    @endif
</div>
