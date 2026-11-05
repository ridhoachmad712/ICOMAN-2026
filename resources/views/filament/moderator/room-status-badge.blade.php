@php
    [$label, $color] = match (true) {
        $room->isLocked() => ['Terkirim', 'info'],
        $room->scoringProgress()['ready'] => ['Siap dikirim', 'success'],
        $room->scoringProgress()['total'] === 0 => ['Belum ada presenter', 'gray'],
        default => ['Perlu dinilai', 'warning'],
    };
@endphp
<x-filament::badge :color="$color">{{ $label }}</x-filament::badge>
