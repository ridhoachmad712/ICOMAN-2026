@php
    $content = app(\App\Services\SectionContent::class);

    // Satu rombongan tanpa pemisahan tingkatan; urutannya murni mengikuti nomor
    // urut yang diatur admin.
    $sponsors = $content->records($section);
    $count = $sponsors->count();

    // Pita berjalan hanya mulus bila isinya lebih lebar dari layar. Dengan
    // sponsor sedikit, satu set diulang sampai cukup panjang — kalau tidak,
    // akan tampak ruang kosong menganga di tengah putaran.
    $repeat = max(1, (int) ceil(16 / max(1, $count)));
    $set = collect()->times($repeat)->flatMap(fn () => $sponsors);

    // Kecepatan dijaga tetap: makin panjang pitanya, makin lama satu putaran.
    $duration = max(30, $set->count() * 4);
@endphp

<section class="border-b border-slate-100 bg-white py-12">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-section-heading :title="$section->heading ?: __('site.our_sponsors')" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
    </div>

    <div class="sponsor-marquee relative overflow-hidden">
        <div class="sponsor-marquee-track flex w-max items-start gap-12" style="animation-duration: {{ $duration }}s">
            @foreach([1, 2] as $copy)
                {{-- Salinan kedua hanya untuk menyambung putaran; pembaca layar cukup membaca yang pertama. --}}
                <div class="sponsor-marquee-set flex items-start gap-12" @if($copy === 2) aria-hidden="true" @endif>
                    @foreach($set as $sponsor)
                        @php $logo = $sponsor->getFirstMediaUrl('logo', 'thumb'); @endphp
                        <div class="group flex shrink-0 flex-col items-center gap-2" title="{{ $sponsor->tierLabel() }}">
                            @if($logo)
                                <img src="{{ $logo }}" alt="{{ $sponsor->name }}" loading="lazy" class="h-14 w-auto object-contain">
                            @else
                                <span class="font-medium whitespace-nowrap text-slate-500">{{ $sponsor->name }}</span>
                            @endif

                            {{-- Ruangnya disediakan sejak awal supaya barisnya tidak
                                 bergeser saat keterangan muncul. --}}
                            <span class="text-[11px] font-semibold uppercase tracking-widest whitespace-nowrap text-[var(--brand)] opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                                {{ $sponsor->tierLabel() }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</section>
