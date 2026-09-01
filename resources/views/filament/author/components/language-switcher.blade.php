@php
    $isIndonesian = app()->getLocale() === 'id';
@endphp

<div class="author-language-switcher">
    <x-filament::dropdown placement="bottom-end" width="xs" teleport>
        <x-slot name="trigger">
            <button
                type="button"
                class="author-language-trigger"
                aria-label="{{ $isIndonesian ? 'Pilih bahasa' : 'Choose language' }}"
            >
                <x-filament::icon icon="heroicon-o-language" class="h-5 w-5 shrink-0 text-gray-500" />
                <span class="author-language-label text-sm font-medium text-gray-700">
                    {{ $isIndonesian ? 'Bahasa Indonesia' : 'English' }}
                </span>
                <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 shrink-0 text-gray-400" />
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item
                tag="a"
                href="{{ route('locale.switch', 'id') }}"
                :color="$isIndonesian ? 'primary' : 'gray'"
                :icon="$isIndonesian ? 'heroicon-m-check' : null"
            >
                Bahasa Indonesia
            </x-filament::dropdown.list.item>

            <x-filament::dropdown.list.item
                tag="a"
                href="{{ route('locale.switch', 'en') }}"
                :color="$isIndonesian ? 'gray' : 'primary'"
                :icon="$isIndonesian ? null : 'heroicon-m-check'"
            >
                English
            </x-filament::dropdown.list.item>
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</div>
