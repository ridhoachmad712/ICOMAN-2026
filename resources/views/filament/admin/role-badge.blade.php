{{-- Menggantikan kolom pencarian global di topbar: menunjukkan PERAN yang sedang
     login (bukan nama pengguna), dengan warna berbeda per peran. --}}
@php $badge = auth()->user()?->roleBadge(); @endphp

@if($badge)
    <x-filament::badge :color="$badge['color']" class="hidden sm:inline-flex">
        {{ $badge['label'] }}
    </x-filament::badge>
@endif
