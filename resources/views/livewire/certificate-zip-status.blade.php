@php
    $state = $status['state'] ?? null;
    $busy = $state === 'running';
    $total = max(1, (int) ($status['total'] ?? 0));
    $done = (int) ($status['done'] ?? 0);
    $percent = $state === 'ready' ? 100 : (int) floor($done / $total * 100);
@endphp

<div @if($busy) wire:poll.500ms="work" @endif>
    @if($status)
        <div class="fi-section" style="display:flex;flex-wrap:wrap;align-items:center;gap:.75rem 1rem;padding:.875rem 1.125rem;border-radius:.75rem;">
            <x-filament::icon
                :icon="match ($state) { 'ready' => 'heroicon-o-check-circle', 'failed' => 'heroicon-o-exclamation-triangle', default => 'heroicon-o-arrow-path' }"
                style="width:1.5rem;height:1.5rem;flex-shrink:0;color:var(--{{ match ($state) { 'ready' => 'success', 'failed' => 'danger', default => 'primary' } }}-600);" />

            <div style="flex:1 1 16rem;min-width:0;font-size:.875rem;line-height:1.25rem;">
                <div style="font-weight:600;">
                    @switch($state)
                        @case('running') Menyiapkan ZIP: {{ $done }} dari {{ $status['total'] }} sertifikat @break
                        @case('ready') ZIP siap: {{ $status['label'] }} ({{ $status['total'] }} sertifikat, {{ \Illuminate\Support\Number::fileSize($status['size'] ?? 0, 1) }}) @break
                        @default Gagal menyiapkan ZIP
                    @endswitch
                </div>
                @if($busy)
                    <div style="margin-top:.5rem;height:.375rem;border-radius:9999px;background:rgba(127,127,127,.2);overflow:hidden;">
                        <div style="height:100%;width:{{ $percent }}%;background:var(--primary-500);transition:width .4s;"></div>
                    </div>
                    <div style="margin-top:.25rem;font-size:.75rem;color:var(--gray-500);">
                        Biarkan halaman ini terbuka sampai selesai. Bila ditinggal, penyusunan berhenti sementara dan berlanjut saat halaman dibuka lagi.
                    </div>
                @elseif($state === 'failed')
                    <div style="font-size:.75rem;color:var(--gray-500);">{{ \Illuminate\Support\Str::limit($status['error'] ?? 'Coba ulangi, atau unduh per kelompok.', 160) }}</div>
                @endif
            </div>

            <div style="display:flex;gap:.5rem;">
                @if($state === 'ready')
                    <x-filament::button tag="a" size="sm" color="success" icon="heroicon-o-arrow-down-tray" href="{{ route('admin.certificates.zip') }}">
                        Unduh ZIP
                    </x-filament::button>
                @endif
                <x-filament::button size="sm" color="gray" wire:click="dismiss">
                    {{ $busy ? 'Batalkan' : 'Tutup' }}
                </x-filament::button>
            </div>
        </div>
    @endif
</div>
