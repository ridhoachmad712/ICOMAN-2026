@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $heroImage = $s->hero_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($s->hero_image) : null;

    // Panel fakta kunci: hanya data yang benar-benar ada, tidak ada angka
    // karangan. Sel yang datanya kosong tidak ditampilkan.
    $dateText = $edition?->start_date
        ? $edition->start_date->translatedFormat('d M Y').($edition->end_date && ! $edition->end_date->equalTo($edition->start_date) ? ' – '.$edition->end_date->translatedFormat('d M Y') : '')
        : null;
    $nextDeadline = $content->nextDeadline();
    $lowestFee = \App\Models\RegistrationFee::query()
        ->when($edition, fn ($query) => $query->where('edition_id', $edition->id))
        ->get()
        ->groupBy('currency')
        ->map(fn ($fees) => $fees->min(fn ($fee) => (float) $fee->currentPrice()));
    $feeCurrency = $lowestFee->has('IDR') ? 'IDR' : $lowestFee->keys()->first();
    $topicCount = \App\Models\Topic::query()
        ->when($edition, fn ($query) => $query->where('edition_id', $edition->id))
        ->count();

    $facts = array_values(array_filter([
        $dateText ? ['calendar', __('site.home_date'), $dateText, $s->event_mode] : null,
        $nextDeadline ? ['clock', __('site.next_deadline'), $nextDeadline->deadlineAt()->translatedFormat('d M Y'), $nextDeadline->label] : null,
        $feeCurrency ? ['ticket', __('site.hero_fee_from'), $feeCurrency.' '.number_format((float) $lowestFee[$feeCurrency], 0, ',', '.'), null] : null,
        $topicCount ? ['academic-cap', __('site.hero_subthemes'), __('site.hero_subthemes_count', ['count' => $topicCount]), null] : null,
    ]));
@endphp

    {{-- HERO --}}
    <section class="on-dark relative bg-[var(--brand-2)] text-white overflow-hidden">
        {{-- Ketiga lapisan di bawah ini bertugas SATU hal: menjaga teks putih
             tetap terbaca di atas gambar apa pun yang dipasang panitia. Tanpa
             lapisan itu rasio kontrasnya berubah-ubah mengikuti gambarnya.
             Karena itu ia bertahan sementara hiasan lain dilepas. --}}
        @if($heroImage)
            <img src="{{ $heroImage }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
            <div class="absolute inset-0 bg-gradient-to-t from-[var(--brand-2)] via-[var(--brand-2)]/80 to-[var(--brand-2)]/40"></div>
        @else
            {{-- Background default: pola SVG self-hosted + wash warna brand --}}
            <img src="{{ asset('images/hero-pattern.svg') }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-br from-[var(--brand)]/25 via-transparent to-[var(--brand-2)]/60"></div>
        @endif
        {{-- Vignette: menggelapkan tepi bawah tempat teks berada. --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(120%_120%_at_50%_0%,transparent_50%,rgba(0,0,0,0.4))]"></div>

        {{-- Dua kolom di layar lebar: ajakan di kiri, fakta yang bisa diperiksa
             di kanan. Dulu sisi kanan kosong dan fakta yang sama muncul dua kali
             (chip di hero dan kartu di bagian Tentang). --}}
        <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-12 lg:items-center lg:gap-12 lg:px-8 lg:py-20">
            <div class="lg:col-span-7">
                {{-- Penanda yang sama dengan seluruh bagian lain; hero tidak
                     dikecualikan, karena motif yang punya pengecualian bukan motif. --}}
                <div class="section-mark"></div>
                <p class="eyebrow">
                    {{ $section->eyebrow ?: __('site.hero_eyebrow') }}
                </p>
                <h1 class="mt-3 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight">
                    {{ $section->heading ?: ($edition?->name ?: $s->conference_name) }}
                </h1>
                <p class="mt-5 max-w-3xl text-xl leading-snug font-medium text-white/90 sm:text-2xl lg:text-3xl">
                    {{ $section->subheading ?: $edition?->theme }}
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <x-participation-link class="btn-accent" :label="__('site.home_submit_abstract')" />
                    <x-participation-link role="non_presenter" class="btn-ghost" :label="__('site.home_attend_seminar')" />
                </div>
                <x-submission-status class="mt-5 max-w-2xl text-white/90" />
            </div>

            @if($facts)
                <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl bg-white/15 ring-1 ring-white/15 lg:col-span-5">
                    @foreach($facts as [$icon, $label, $value, $note])
                        <div class="bg-[var(--brand-2)]/85 p-4 backdrop-blur sm:p-5">
                            <dt class="flex items-center gap-2 text-sm text-white/75">
                                <x-ui-icon :name="$icon" class="h-4 w-4 shrink-0 text-[var(--brand-ink)]" />
                                {{ $label }}
                            </dt>
                            <dd class="mt-1.5 font-display text-lg font-bold leading-tight text-white sm:text-xl">{{ $value }}</dd>
                            @if(filled($note))
                                <dd class="mt-1 text-xs leading-snug text-white/75 sm:text-sm">{{ $note }}</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </section>
