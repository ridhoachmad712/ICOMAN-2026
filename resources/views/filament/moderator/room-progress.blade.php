{{-- Kemajuan satu room. Gaya sebaris: panel admin memakai CSS bawaan Filament, tanpa build Tailwind sendiri. --}}
@php
    $progress = $room->scoringProgress();
    $color = $room->isLocked() || $progress['ready'] ? 'success' : 'primary';
@endphp
<div style="display:flex;flex-direction:column;gap:.5rem;">
    <div style="display:flex;justify-content:space-between;gap:1rem;font-size:.875rem;">
        <span style="font-weight:600;">{{ $progress['done'] }} dari {{ $progress['total'] }} presenter selesai</span>
        <span style="color:var(--gray-500);">{{ $progress['percent'] }}%</span>
    </div>
    <div role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100" style="height:.5rem;border-radius:9999px;background:rgba(128,128,128,.25);overflow:hidden;">
        <div style="height:100%;width:{{ $progress['percent'] }}%;border-radius:9999px;background:var(--{{ $color }}-500);"></div>
    </div>
    <p style="margin:0;font-size:.875rem;color:var(--gray-500);">
        @if($room->isLocked())
            Penilaian sudah dikirim dan terkunci.
        @elseif($progress['ready'])
            Semua presenter selesai. Penilaian siap dikirim.
        @else
            Masih kurang: {{ $room->scoringGap() }}
        @endif
        @if($progress['absent'])
            {{ $progress['absent'] }} presenter tidak hadir dan tidak perlu dinilai.
        @endif
    </p>
</div>
