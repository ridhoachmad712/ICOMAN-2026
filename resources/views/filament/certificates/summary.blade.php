{{-- Ringkasan halaman Sertifikat: jumlah yang berlaku, yang menunggu diterbitkan,
     dan yang dicabut, ditambah aturan penerbitan yang bisa dibuka bila perlu.
     Panel admin tidak memakai build Tailwind sendiri, jadi gayanya inline. --}}
@php
    $edition = currentEdition();
    $base = \App\Models\Certificate::query()->where('edition_id', $edition?->id);
    $valid = (clone $base)->valid()->count();
    $revoked = (clone $base)->whereNotNull('revoked_at')->count();
    $manual = (clone $base)->manual()->count();
    $pending = $edition
        ? app(\App\Services\CertificateIssuer::class)->pendingCounts($edition)
        : ['presentation' => 0, 'participation' => 0];

    $tiles = [
        ['Berlaku', $valid, $manual > 0 ? "termasuk {$manual} manual" : 'sudah bisa diunduh', 'heroicon-o-check-badge', 'success'],
        ['Siap terbit: presenter', $pending['presentation'], $pending['presentation'] > 0 ? 'Terbitkan → Sertifikat presenter' : 'tidak ada yang menunggu', 'heroicon-o-presentation-chart-bar', $pending['presentation'] > 0 ? 'warning' : 'gray'],
        ['Siap terbit: peserta seminar', $pending['participation'], $pending['participation'] > 0 ? 'Terbitkan → Sertifikat peserta seminar' : 'tidak ada yang menunggu', 'heroicon-o-user-group', $pending['participation'] > 0 ? 'warning' : 'gray'],
        ['Dicabut', $revoked, $revoked > 0 ? 'bisa diterbitkan ulang' : 'tidak ada', 'heroicon-o-no-symbol', $revoked > 0 ? 'danger' : 'gray'],
    ];
@endphp

<div style="display:flex;flex-direction:column;gap:.75rem;">
    <div style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,13rem),1fr));">
        @foreach($tiles as [$label, $value, $hint, $icon, $color])
            <div class="fi-section" style="display:flex;align-items:center;gap:.875rem;padding:1rem 1.125rem;border-radius:.75rem;">
                <div style="display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;border-radius:.625rem;flex-shrink:0;background:color-mix(in srgb, var(--{{ $color }}-500) 14%, transparent);color:var(--{{ $color }}-600);">
                    <x-filament::icon :icon="$icon" style="width:1.375rem;height:1.375rem;" />
                </div>
                <div style="min-width:0;">
                    <div style="font-size:.75rem;line-height:1rem;color:var(--gray-500);">{{ $label }}</div>
                    <div style="font-size:1.5rem;line-height:1.875rem;font-weight:700;">{{ number_format($value, 0, ',', '.') }}</div>
                    <div style="font-size:.75rem;line-height:1rem;color:var(--gray-500);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $hint }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <details class="fi-section" style="border-radius:.75rem;padding:.75rem 1.125rem;font-size:.875rem;line-height:1.5rem;">
        <summary style="cursor:pointer;font-weight:600;">Siapa yang mendapat sertifikat?</summary>
        <ul style="margin:.5rem 0 .25rem;padding-left:1.25rem;list-style:disc;color:var(--gray-600);">
            <li><b>Presenter</b>: satu sertifikat per penulis paper (termasuk co-author), bila paper diterima, full paper sudah masuk, invoice lunas, dan moderator mencatatnya hadir atau diwakili.</li>
            <li><b>Peserta seminar</b>: setiap peserta yang invoice-nya lunas. Presenter yang memenuhi syarat di atas juga menerimanya, jadi memegang keduanya.</li>
            <li>Sertifikat otomatis diunduh peserta dari dashboard portal. <b>Sertifikat manual</b> tidak terkait akun: unduh PDF-nya dari tabel lalu kirimkan sendiri.</li>
            <li>Menekan <b>Terbitkan</b> berulang kali aman: yang sudah punya sertifikat tidak dibuat dobel.</li>
        </ul>
    </details>
</div>
