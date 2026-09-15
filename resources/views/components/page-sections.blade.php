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

    <div class="ps-edit-add" x-data>
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
         @if($editing)
             x-data
             data-section-id="{{ $section->id }}"
             draggable="true"
             x-on:dragstart="window.psDragging = {{ $section->id }}; $el.classList.add('ps-dragging')"
             x-on:dragend="window.psDragging = null; $el.classList.remove('ps-dragging')"
             x-on:dragover.prevent="$el.classList.add('ps-drop-target')"
             x-on:dragleave="$el.classList.remove('ps-drop-target')"
             x-on:drop.prevent="
                 $el.classList.remove('ps-drop-target');
                 if (window.psDragging && window.psDragging !== {{ $section->id }}) {
                     Livewire.dispatch('ps-drop', { dragged: window.psDragging, target: {{ $section->id }} });
                 }
             "
         @endif>

        @if($editing)
            {{-- Aksi dikirim sebagai event Livewire, bukan wire:click: toolbar ini
                 berada di luar akar komponen editor, sehingga wire:click di sini
                 tidak akan pernah sampai. --}}
            <div class="ps-edit-toolbar">
                <span class="ps-edit-handle" title="Seret untuk memindahkan">⠿</span>
                <span class="ps-edit-label">{{ $section->typeLabel() }}</span>
                @if(! $section->is_published)<span class="ps-edit-flag">disembunyikan</span>@endif
                @if($isEmpty)<span class="ps-edit-flag">belum ada isi</span>@endif

                <button type="button" title="Naikkan"
                        x-on:click="Livewire.dispatch('ps-move', { id: {{ $section->id }}, direction: -1 })">↑</button>
                <button type="button" title="Turunkan"
                        x-on:click="Livewire.dispatch('ps-move', { id: {{ $section->id }}, direction: 1 })">↓</button>
                <button type="button" title="Tampil / sembunyi"
                        x-on:click="Livewire.dispatch('ps-toggle', { id: {{ $section->id }} })">
                    {{ $section->is_published ? '👁' : '🚫' }}
                </button>
                <button type="button" title="Gandakan"
                        x-on:click="Livewire.dispatch('ps-duplicate', { id: {{ $section->id }} })">⧉</button>
                <button type="button" title="Pengaturan"
                        x-on:click="Livewire.dispatch('ps-edit', { id: {{ $section->id }} })">⚙</button>
                <button type="button" title="Hapus" class="ps-edit-danger"
                        x-on:click="confirm('Hapus blok ini dari halaman?') && Livewire.dispatch('ps-remove', { id: {{ $section->id }} })">✕</button>
            </div>
        @endif

        @if($section->view())
            @include($section->view(), ['section' => $section])
        @endif
    </div>

    @if($editing)
        <div class="ps-edit-add" x-data>
            <button type="button" class="ps-edit-add-button"
                    x-on:click="$dispatch('open-add-block', { after: {{ $section->id }} })">+ Tambah blok di sini</button>
        </div>
    @endif
@endforeach
