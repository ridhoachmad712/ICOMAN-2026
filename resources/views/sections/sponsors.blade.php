@php
    $content = app(\App\Services\SectionContent::class);

    // Satu rombongan tanpa pemisahan tingkatan; urutannya murni mengikuti nomor
    // urut yang diatur admin.
    $sponsors = $content->records($section);
    $count = $sponsors->count();

    // Pita berjalan perlu isi yang cukup panjang agar putarannya mulus. Bila
    // sponsornya sedikit, satu set diulang sampai memenuhi lebar layar.
    $repeat = max(1, (int) ceil(8 / max(1, $count)));
    $set = collect()->times($repeat)->flatMap(fn () => $sponsors);

    // Kecepatan dijaga tetap: makin banyak logo, makin lama satu putaran.
    $duration = max(24, $set->count() * 4);
@endphp

<section class="bg-white py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-section-heading :title="$section->heading ?: __('site.our_sponsors')" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
    </div>

    <div class="sponsor-marquee relative overflow-hidden">
        <div class="sponsor-marquee-track flex w-max items-center gap-12" style="animation-duration: {{ $duration }}s">
            @foreach([1, 2] as $copy)
                {{-- Salinan kedua hanya untuk menyambung putaran; pembaca layar cukup membaca yang pertama. --}}
                <div class="sponsor-marquee-set flex items-center gap-12" @if($copy === 2) aria-hidden="true" @endif>
                    @foreach($set as $sponsor)
                        @php $logo = $sponsor->getFirstMediaUrl('logo', 'thumb'); @endphp
                        <div class="shrink-0 grayscale transition hover:grayscale-0">
                            @if($logo)
                                <img src="{{ $logo }}" alt="{{ $sponsor->name }}" loading="lazy" class="h-14 w-auto object-contain">
                            @else
                                <span class="font-medium whitespace-nowrap text-slate-400">{{ $sponsor->name }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</section>
