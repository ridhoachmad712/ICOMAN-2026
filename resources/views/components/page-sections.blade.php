@props(['target'])

@php
    $sections = \App\Models\PageSection::forTarget($target);
    $content = app(\App\Services\SectionContent::class);
@endphp

@foreach($sections as $section)
    {{-- Blok tanpa isi dilewati, bukan dirender sebagai ruang kosong. --}}
    @continue($content->isEmpty($section))

    @php $style = $section->styleVariables(); @endphp

    {{-- Pembungkus membawa pilihan tampilan admin sebagai custom property.
         Blok yang tidak diatur tidak mendapat pembungkus bergaya sama sekali,
         jadi tampilannya persis seperti bawaannya. --}}
    <div @class(['ps-block', 'ps-cols-'.$section->columnCount() => $section->columnCount()]) @if($style) style="{{ $style }}" @endif>
        @include($section->view(), ['section' => $section])
    </div>
@endforeach
