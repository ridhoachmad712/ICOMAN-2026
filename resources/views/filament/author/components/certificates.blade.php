{{-- Sertifikat yang terbit lewat akun ini. Presenter menerima satu per
     penulis paper (termasuk co-author) untuk diteruskan ke rekan penulisnya. --}}
@php
    $id = app()->getLocale() === 'id';
    $certificates = $author->certificates()
        ->valid()
        ->when(currentEdition(), fn ($query, $edition) => $query->where('edition_id', $edition->id))
        ->orderBy('number')
        ->get();
@endphp

@if($certificates->isNotEmpty())
    <section class="rounded-xl border border-success-200 bg-white p-5 dark:border-success-500/20 dark:bg-white/5">
        <div class="flex items-start gap-3">
            <x-filament::icon icon="heroicon-o-check-badge" class="mt-0.5 h-6 w-6 shrink-0 text-success-600 dark:text-success-400" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-gray-950 dark:text-white">{{ $id ? 'Sertifikat' : 'Certificates' }}</p>
                <p class="mt-1 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                    {{ $certificates->count() > 1
                        ? ($id ? 'Sertifikat untuk setiap penulis paper Anda. Teruskan kepada rekan penulis masing-masing.' : 'One certificate for each author of your paper. Please forward them to your co-authors.')
                        : ($id ? 'Sertifikat Anda sudah terbit. Keasliannya dapat diperiksa lewat QR code di sertifikat.' : 'Your certificate has been issued. Its authenticity can be checked through the QR code on it.') }}
                </p>
                <ul class="mt-3 divide-y divide-gray-100 dark:divide-white/10">
                    @foreach($certificates as $certificate)
                        <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-950 dark:text-white">{{ $certificate->recipient_name }}</p>
                                <p class="text-xs text-gray-500">{{ $certificate->typeLabel() }} · No. {{ $certificate->number }}</p>
                            </div>
                            <x-filament::button tag="a" size="sm" color="success" outlined icon="heroicon-o-arrow-down-tray"
                                                href="{{ route('author.certificates.download', $certificate) }}" class="shrink-0">
                                {{ $id ? 'Unduh PDF' : 'Download PDF' }}
                            </x-filament::button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
@endif
