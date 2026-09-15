@props(['target'])

@php
    $sections = \App\Models\PageSection::forTarget($target);
    $content = app(\App\Services\SectionContent::class);
    $editing = pageEditMode();
@endphp

@if($editing)
    {{-- Bar, panel pengaturan, dan daftar blok. Hanya dimuat dalam mode sunting,
         jadi pengunjung biasa tidak menerima tambahan apa pun. --}}
    @livewire('page-editor', ['target' => $target])

    <div class="h-10"></div>

    <div class="ps-edit-add">
        <button type="button" class="ps-edit-add-button"
                x-on:click="$dispatch('open-add-block', { after: null })">+ Tambah blok di awal</button>
    </div>
@endif

@foreach($sections as $section)
    @php $isEmpty = $content->isEmpty($section); @endphp

    {{-- Dalam mode sunting, blok kosong dan yang disembunyikan tetap ditampilkan
         (diberi tanda) — kalau tidak, tidak ada yang bisa diklik untuk diisi. --}}
    @continue($isEmpty && ! $editing)

    @php $style = $section->styleVariables(); @endphp

    <div @class([
            'ps-block',
            'ps-cols-'.$section->columnCount() => $section->columnCount(),
            'ps-editable' => $editing,
            'ps-hidden-block' => $editing && ! $section->is_published,
        ])
         @if($style) style="{{ $style }}" @endif
         @if($editing) data-section-id="{{ $section->id }}" @endif>

        @if($editing)
            <div class="ps-edit-toolbar">
                <span class="ps-edit-label">{{ $section->typeLabel() }}</span>
                @if(! $section->is_published)<span class="ps-edit-flag">disembunyikan</span>@endif
                @if($isEmpty)<span class="ps-edit-flag">belum ada isi</span>@endif

                <button type="button" title="Naikkan" wire:click="move({{ $section->id }}, -1)">↑</button>
                <button type="button" title="Turunkan" wire:click="move({{ $section->id }}, 1)">↓</button>
                <button type="button" title="Tampil / sembunyi" wire:click="toggleVisibility({{ $section->id }})">
                    {{ $section->is_published ? '👁' : '🚫' }}
                </button>
                <button type="button" title="Gandakan" wire:click="duplicate({{ $section->id }})">⧉</button>
                <button type="button" title="Pengaturan" wire:click="edit({{ $section->id }})">⚙</button>
                <button type="button" title="Hapus" class="ps-edit-danger"
                        wire:click="remove({{ $section->id }})"
                        wire:confirm="Hapus blok ini dari halaman?">✕</button>
            </div>
        @endif

        @if($section->view())
            @include($section->view(), ['section' => $section])
        @endif
    </div>

    @if($editing)
        <div class="ps-edit-add">
            <button type="button" class="ps-edit-add-button"
                    x-on:click="$dispatch('open-add-block', { after: {{ $section->id }} })">+ Tambah blok di sini</button>
        </div>
    @endif
@endforeach
