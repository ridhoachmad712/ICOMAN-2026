@php
    $id = app()->getLocale() === 'id';
    $valid = $certificate && ! $certificate->isRevoked();
@endphp

{{-- Tujuan QR di sertifikat. Tidak diindeks mesin pencari: halaman ini hanya
     untuk orang yang memegang sertifikatnya. --}}
<x-layout :title="$id ? 'Verifikasi Sertifikat' : 'Certificate Verification'" noindex>
    <x-page-header :title="$id ? 'Verifikasi Sertifikat' : 'Certificate Verification'" />

    <section class="mx-auto max-w-2xl px-4 py-12 sm:px-6">
        @if($valid)
            <div class="card overflow-hidden">
                <div class="flex items-center gap-3 bg-emerald-600 px-6 py-4 text-white">
                    <x-ui-icon name="check-circle" class="h-7 w-7 shrink-0" />
                    <div>
                        <p class="text-lg font-bold">{{ $id ? 'Sertifikat sah' : 'Valid certificate' }}</p>
                        <p class="text-sm text-white/90">{{ $id ? 'Diterbitkan oleh panitia' : 'Issued by the organising committee of' }} {{ $certificate->edition?->name }}</p>
                    </div>
                </div>
                <dl class="divide-y divide-slate-100 px-6">
                    @foreach(array_filter([
                        ($id ? 'Nama' : 'Name') => $certificate->recipient_name,
                        ($id ? 'Afiliasi' : 'Affiliation') => $certificate->recipient_affiliation,
                        ($id ? 'Jenis' : 'Type') => $certificate->typeLabel(),
                        ($id ? 'Judul paper' : 'Paper title') => $certificate->paper_title,
                        ($id ? 'Nomor sertifikat' : 'Certificate number') => $certificate->number,
                        ($id ? 'Tanggal terbit' : 'Issued on') => $certificate->issued_at->translatedFormat('d F Y'),
                    ], fn ($value) => filled($value)) as $label => $value)
                        <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                            <dt class="text-sm text-slate-500">{{ $label }}</dt>
                            <dd class="font-medium text-slate-900 sm:col-span-2">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @elseif($certificate)
            <div class="card border-l-4 border-red-600 p-6">
                <p class="text-lg font-bold text-red-700">{{ $id ? 'Sertifikat telah dicabut' : 'This certificate has been revoked' }}</p>
                <p class="mt-2 text-slate-600">
                    {{ $id ? 'Nomor' : 'Number' }} {{ $certificate->number }}
                    {{ $id ? 'tidak lagi berlaku sejak' : 'is no longer valid since' }} {{ $certificate->revoked_at->translatedFormat('d F Y') }}.
                    {{ $id ? 'Hubungi panitia untuk keterangan lebih lanjut.' : 'Please contact the committee for details.' }}
                </p>
            </div>
        @else
            <div class="card border-l-4 border-red-600 p-6">
                <p class="text-lg font-bold text-red-700">{{ $id ? 'Sertifikat tidak ditemukan' : 'Certificate not found' }}</p>
                <p class="mt-2 text-slate-600">
                    {{ $id
                        ? 'Kode pada QR ini tidak terdaftar. Sertifikat mungkin palsu, atau sudah diterbitkan ulang dengan kode baru. Hubungi panitia bila ragu.'
                        : 'This QR code is not registered. The certificate may be forged, or it was reissued with a new code. Contact the committee if in doubt.' }}
                </p>
            </div>
        @endif
    </section>
</x-layout>
