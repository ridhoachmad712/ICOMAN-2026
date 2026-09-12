@props(['target'])

@php
    $sections = \App\Models\PageSection::forTarget($target);
    $content = app(\App\Services\SectionContent::class);
@endphp

@foreach($sections as $section)
    {{-- Blok tanpa isi dilewati, bukan dirender sebagai ruang kosong. --}}
    @continue($content->isEmpty($section))

    @include($section->view(), ['section' => $section])
@endforeach
