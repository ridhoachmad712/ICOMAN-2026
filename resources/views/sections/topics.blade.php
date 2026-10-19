@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $heading = $section->heading;
    $eyebrow = $section->eyebrow;
    $subheading = $section->subheading;
@endphp

@php $records = $content->records($section); @endphp

    {{-- CALL FOR PAPERS TEASER --}}
    {{-- Sub-tema adalah inti undangan menulis, jadi tiap sub-tema mendapat
         kartu bernomor. Awalan "Sub-tema 1:" yang diketik panitia dipindah ke
         label kecil agar judulnya terbaca utuh tanpa diulang. --}}
    <section class="section-y bg-white">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <x-section-heading :section="$section" :title="$heading" :eyebrow="$eyebrow" :subtitle="$subheading" />
            <ol class="grid gap-4 sm:grid-cols-2">
                @foreach($records->take(8) as $topic)
                    @php
                        $title = (string) $topic->title;
                        $prefixPattern = '/^\s*(sub-?\s?(?:theme|tema|topic|topik)\s*\d+)\s*[:.\-–—]\s*/iu';
                        $prefix = preg_match($prefixPattern, $title, $match) ? trim($match[1]) : null;
                        $cleanTitle = $prefix ? preg_replace($prefixPattern, '', $title) : $title;
                    @endphp
                    <li data-reveal class="card flex gap-4 p-5 sm:p-6">
                        <span aria-hidden="true" class="font-display text-3xl font-bold leading-none text-[var(--brand)]">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <div>
                            @if($prefix)<p class="text-sm font-medium text-slate-500">{{ $prefix }}</p>@endif
                            <h3 class="mt-0.5 text-lg font-semibold leading-snug text-[var(--brand-2)]">{{ $cleanTitle }}</h3>
                        </div>
                    </li>
                @endforeach
            </ol>
            <div class="text-center mt-6">
                <a href="{{ route('call-for-papers') }}" class="link-more">{{ __('site.view_topics') }}</a>
            </div>
        </div>
    </section>
