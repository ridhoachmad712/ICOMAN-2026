<x-layout :metaDescription="$aboutPage?->meta_description">
    @php
        $s = siteSettings();
        $heroImage = $s->hero_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($s->hero_image) : null;
        $tierOrder = ['platinum' => 'Platinum', 'gold' => 'Gold', 'silver' => 'Silver', 'partner' => 'Partner', 'media_partner' => 'Media Partner'];

        // JSON-LD Event (structured data untuk Google).
        $mode = strtolower((string) $s->event_mode);
        $attendance = str_contains($mode, 'hybrid') ? 'MixedEventAttendanceMode'
            : (str_contains($mode, 'online') ? 'OnlineEventAttendanceMode' : 'OfflineEventAttendanceMode');
        $ld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => ($edition?->name ?? 'ICOMAN 2026').($edition?->theme ? ' — '.$edition->theme : ''),
            'startDate' => $edition?->start_date?->toIso8601String(),
            'endDate' => ($edition?->end_date ?? $edition?->start_date)?->toIso8601String(),
            'eventAttendanceMode' => 'https://schema.org/'.$attendance,
            'eventStatus' => 'https://schema.org/EventScheduled',
            'url' => route('home'),
            'image' => $heroImage ?: asset('images/hero-pattern.svg'),
            'description' => \Illuminate\Support\Str::limit(strip_tags((string) $aboutPage?->content), 300, ''),
            'location' => $s->event_location ? [
                '@type' => 'Place',
                'name' => $s->event_location,
                'address' => $s->contact_address ?: $s->event_location,
            ] : null,
            'organizer' => [
                '@type' => 'Organization',
                'name' => $s->conference_name ?: 'ICOMAN 2026',
                'email' => $s->contact_email,
            ],
        ]);
    @endphp

    <x-slot:head>
        <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    </x-slot:head>

    {{-- HERO --}}
    <section class="relative bg-[var(--brand-2)] text-white overflow-hidden">
        @if($heroImage)
            <img src="{{ $heroImage }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
            <div class="absolute inset-0 bg-gradient-to-t from-[var(--brand-2)] via-[var(--brand-2)]/80 to-[var(--brand-2)]/40"></div>
        @else
            {{-- Background default: pola SVG self-hosted + wash warna brand --}}
            <img src="{{ asset('images/hero-pattern.svg') }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-br from-[var(--brand)]/25 via-transparent to-[var(--brand-2)]/60"></div>
        @endif

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20 sm:py-28">
            <p class="text-sm font-semibold uppercase tracking-widest text-[var(--brand)]">
                {{ $edition?->name ?? 'ICOMAN 2026' }}
            </p>
            <h1 class="mt-3 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight max-w-4xl">
                {{ $edition?->theme ?: 'International Conference on Management' }}
            </h1>

            {{-- Info chips --}}
            <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-slate-200">
                @if($edition?->start_date)
                    <span class="inline-flex items-center gap-2">📅
                        {{ $edition->start_date->translatedFormat('d M Y') }}@if($edition->end_date && ! $edition->end_date->equalTo($edition->start_date)) – {{ $edition->end_date->translatedFormat('d M Y') }}@endif
                    </span>
                @endif
                @if($s->event_location)<span class="inline-flex items-center gap-2">📍 {{ $s->event_location }}</span>@endif
                @if($s->event_mode)<span class="inline-flex items-center gap-2">💻 {{ $s->event_mode }}</span>@endif
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('author.register') }}" class="btn btn-primary">{{ __('site.submit_paper') }}</a>
                <a href="{{ route('author.registration.create') }}" class="btn btn-ghost">{{ __('site.register_now') }}</a>
            </div>

            @if($edition?->start_date)
                <div class="mt-12">
                    <p class="text-xs uppercase tracking-widest text-white/60 mb-3">{{ __('site.countdown_to') }}</p>
                    <x-countdown :date="$edition->start_date" />
                </div>
            @endif
        </div>
    </section>

    {{-- STATS STRIP (banner, mengangkat dari hero) --}}
    @if($stats['speakers'] || $stats['topics'])
        <section class="relative z-10 -mt-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div data-reveal class="rounded-2xl bg-gradient-to-r from-[var(--brand)] to-[var(--brand-2)] text-white shadow-xl grid grid-cols-3 divide-x divide-white/15 py-6">
                    @foreach([['speakers','speakers_count'],['topics','topics_count'],['countries','countries_count']] as [$key,$label])
                        <div class="text-center px-2">
                            <div class="font-display text-3xl sm:text-4xl font-bold">{{ $stats[$key] }}</div>
                            <div class="text-[11px] uppercase tracking-[0.15em] text-white/70 mt-1">{{ __('site.'.$label) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ORGANIZED BY --}}
    @if($s->organizer_name || $s->organizer_logo)
        @php $orgLogo = $s->organizer_logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($s->organizer_logo) : null; @endphp
        <section class="py-8 border-b border-slate-100">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-center gap-4 text-center">
                <span class="text-xs uppercase tracking-widest text-slate-400">{{ app()->getLocale() === 'id' ? 'Diselenggarakan oleh' : 'Organized by' }}</span>
                @if($orgLogo)<img src="{{ $orgLogo }}" alt="{{ $s->organizer_name }}" loading="lazy" class="h-10 w-auto object-contain">@endif
                @if($s->organizer_name)<span class="font-semibold text-[var(--brand-2)]">{{ $s->organizer_name }}</span>@endif
            </div>
        </section>
    @endif

    {{-- ABOUT SUMMARY (2 kolom) --}}
    @if($aboutPage)
        <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20">
            <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
                <div data-reveal>
                    <x-section-heading :title="$aboutPage->title" :eyebrow="__('nav.about')" :center="false" />
                    <div class="prose prose-slate max-w-none">
                        {!! \Illuminate\Support\Str::limit(strip_tags($aboutPage->content), 500) !!}
                    </div>
                    <a href="{{ route('about') }}" class="mt-6 inline-block text-[var(--brand)] font-medium hover:underline">{{ __('site.learn_more') }} →</a>
                </div>
                <div data-reveal class="card p-8 space-y-5">
                    @if($edition?->start_date)
                        <div class="flex items-start gap-3">
                            <span class="text-2xl">📅</span>
                            <div><div class="text-xs uppercase tracking-wide text-slate-400">{{ app()->getLocale() === 'id' ? 'Tanggal' : 'Date' }}</div>
                            <div class="font-semibold text-[var(--brand-2)]">{{ $edition->start_date->translatedFormat('d M Y') }}@if($edition->end_date && ! $edition->end_date->equalTo($edition->start_date)) – {{ $edition->end_date->translatedFormat('d M Y') }}@endif</div></div>
                        </div>
                    @endif
                    @if($s->event_location)
                        <div class="flex items-start gap-3">
                            <span class="text-2xl">📍</span>
                            <div><div class="text-xs uppercase tracking-wide text-slate-400">{{ app()->getLocale() === 'id' ? 'Lokasi' : 'Location' }}</div>
                            <div class="font-semibold text-[var(--brand-2)]">{{ $s->event_location }}</div></div>
                        </div>
                    @endif
                    @if($s->event_mode)
                        <div class="flex items-start gap-3">
                            <span class="text-2xl">💻</span>
                            <div><div class="text-xs uppercase tracking-wide text-slate-400">{{ app()->getLocale() === 'id' ? 'Format' : 'Format' }}</div>
                            <div class="font-semibold text-[var(--brand-2)]">{{ $s->event_mode }}</div></div>
                        </div>
                    @endif
                    <a href="{{ route('registration') }}" class="btn btn-primary w-full">{{ __('site.register_now') }}</a>
                </div>
            </div>
        </section>
    @endif

    {{-- SPEAKERS --}}
    @if($speakers->isNotEmpty())
        @php
            $spotlight = $speakers->firstWhere('type', 'keynote') ?? $speakers->first();
            $gridSpeakers = $speakers->reject(fn ($sp) => $spotlight && $sp->id === $spotlight->id);
        @endphp
        <section class="bg-white py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.keynote_speakers')" />

                {{-- Keynote spotlight --}}
                @if($spotlight)
                    @php
                        $spPhoto = $spotlight->getFirstMediaUrl('photo', 'card');
                        $spFlag = countryCode($spotlight->country);
                    @endphp
                    <div class="mb-10 grid gap-6 sm:grid-cols-3 items-center card p-6 sm:p-8">
                        <div class="aspect-square sm:aspect-auto sm:h-56 rounded-xl bg-slate-200 overflow-hidden flex items-center justify-center">
                            @if($spPhoto)
                                <img src="{{ $spPhoto }}" alt="{{ $spotlight->name }}" class="h-full w-full object-cover">
                            @else
                                <span class="text-5xl font-bold text-slate-300">{{ mb_substr($spotlight->name, 0, 1) }}</span>
                            @endif
                        </div>
                        <div class="sm:col-span-2">
                            <span class="inline-block text-[10px] uppercase tracking-widest font-semibold text-[var(--brand)] bg-[var(--brand)]/10 px-2 py-0.5 rounded">{{ ucfirst($spotlight->type) }}</span>
                            <h3 class="mt-2 text-2xl font-bold text-[var(--brand-2)]">
                                {{ $spotlight->title_degree ? $spotlight->title_degree.' ' : '' }}{{ $spotlight->name }}
                            </h3>
                            <p class="text-slate-500 inline-flex items-center gap-2">
                                @if($spFlag)<span class="fi fi-{{ strtolower($spFlag) }} rounded-[2px] ring-1 ring-black/5"></span>@endif
                                {{ $spotlight->affiliation }}
                            </p>
                            @if($spotlight->topic)<p class="mt-3 text-slate-700 font-medium">“{{ $spotlight->topic }}”</p>@endif
                            @if($spotlight->bio)<p class="mt-2 text-sm text-slate-500 line-clamp-3">{{ strip_tags($spotlight->bio) }}</p>@endif
                        </div>
                    </div>
                @endif

                @if($gridSpeakers->isNotEmpty())
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach($gridSpeakers as $speaker)
                            <x-card-speaker :speaker="$speaker" />
                        @endforeach
                    </div>
                @endif

                <div class="text-center mt-8">
                    <a href="{{ route('speakers') }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('site.view_all') }} →</a>
                </div>
            </div>
        </section>
    @endif

    {{-- CALL FOR PAPERS TEASER --}}
    @if($topics->isNotEmpty())
        <section class="py-16">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.call_for_papers')" :subtitle="__('site.topics')" />
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($topics->take(8) as $topic)
                        <div class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
                            <span class="mt-1 h-2 w-2 rounded-full bg-[var(--brand)] shrink-0"></span>
                            <span class="text-slate-700">{{ $topic->title }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="text-center mt-8">
                    <a href="{{ route('call-for-papers') }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('site.view_topics') }} →</a>
                </div>
            </div>
        </section>
    @endif

    {{-- REGISTRATION TEASER --}}
    @if($fees->isNotEmpty())
        <section class="bg-white py-16">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.registration_fees')" />
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($fees->take(3) as $fee)
                        @php $amount = $fee->price_early_bird ?? $fee->price_regular; @endphp
                        <div class="card card-hover p-6 text-center">
                            <h3 class="font-semibold text-[var(--brand-2)]">{{ $fee->category }}</h3>
                            <div class="mt-3 text-2xl font-bold text-slate-900">{{ $fee->currency }} {{ number_format((float) $amount, 0, ',', '.') }}</div>
                            @if($fee->price_early_bird)<div class="text-xs text-emerald-600 font-semibold mt-1">{{ __('site.early_bird') }}</div>@endif
                        </div>
                    @endforeach
                </div>
                <div class="text-center mt-8">
                    <a href="{{ route('registration') }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('site.view_fees') }} →</a>
                </div>
            </div>
        </section>
    @endif

    {{-- IMPORTANT DATES --}}
    @if($importantDates->isNotEmpty())
        <section class="py-16">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.important_dates')" />

                @if($nextDeadline)
                    <div class="mb-6 flex flex-col sm:flex-row items-center justify-between gap-3 rounded-xl bg-[var(--brand)] text-white px-6 py-4">
                        <div>
                            <div class="text-xs uppercase tracking-widest text-white/70">{{ __('site.next_deadline') }}</div>
                            <div class="font-semibold">{{ $nextDeadline->label }}</div>
                        </div>
                        <div class="text-lg font-bold">{{ $nextDeadline->date->translatedFormat('d M Y') }}</div>
                    </div>
                @endif

                <ul class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white overflow-hidden">
                    @foreach($importantDates as $d)
                        <li class="flex items-center justify-between gap-4 px-5 py-4 {{ $d->is_highlighted ? 'bg-[var(--brand)]/5' : '' }}">
                            <span class="font-medium text-slate-700">{{ $d->label }}</span>
                            <span class="text-sm font-semibold text-[var(--brand-2)] whitespace-nowrap">
                                {{ $d->date?->translatedFormat('d M Y') ?? 'TBA' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- PUBLICATION & INDEXING --}}
    @if($publicationPage)
        <section class="bg-white py-16">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="$publicationPage->title ?: __('site.publication_indexing')" />
                <div class="prose prose-slate max-w-none mx-auto text-center">
                    {!! \Illuminate\Support\Str::limit(strip_tags($publicationPage->content), 600) !!}
                </div>
            </div>
        </section>
    @endif

    {{-- GALLERY HIGHLIGHT --}}
    @if($galleries->isNotEmpty())
        <section class="py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.gallery')" />
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    @foreach($galleries as $g)
                        @php $img = $g->getFirstMediaUrl('image', 'thumb'); @endphp
                        <div class="aspect-square rounded-lg overflow-hidden bg-slate-100">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $g->caption ?? '' }}" loading="lazy" class="h-full w-full object-cover hover:scale-105 transition-transform duration-300">
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- LATEST NEWS --}}
    @if($latestNews->isNotEmpty())
        <section class="bg-white py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.latest_news')" />
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($latestNews as $item)
                        <x-card-news :item="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- FAQ TEASER --}}
    @if($faqs->isNotEmpty())
        <section class="section-tint py-16">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.faq_title')" />
                <div class="space-y-3">
                    @foreach($faqs as $faq)
                        <div x-data="{ open: false }" class="rounded-lg border border-slate-200 overflow-hidden">
                            <button @click="open = !open" :aria-expanded="open" class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left">
                                <span class="font-medium text-slate-800">{{ $faq->question }}</span>
                                <svg class="h-5 w-5 text-slate-400 transition-transform shrink-0" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-transition x-cloak class="px-5 pb-4 text-slate-600 whitespace-pre-line">{{ $faq->answer }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="text-center mt-6">
                    <a href="{{ route('faq') }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('site.view_all_faq') }} →</a>
                </div>
            </div>
        </section>
    @endif

    {{-- SPONSORS (grouped by tier) --}}
    @if($sponsors->isNotEmpty())
        <section class="bg-white py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.our_sponsors')" />
                <div class="space-y-8">
                    @foreach($tierOrder as $tierKey => $tierLabel)
                        @if($sponsors->has($tierKey))
                            <div>
                                <p class="text-center text-xs uppercase tracking-widest text-slate-400 mb-4">{{ $tierLabel }}</p>
                                <div class="flex flex-wrap items-center justify-center gap-8">
                                    @foreach($sponsors->get($tierKey) as $sponsor)
                                        @php $logo = $sponsor->getFirstMediaUrl('logo', 'thumb'); @endphp
                                        <div class="grayscale hover:grayscale-0 transition">
                                            @if($logo)
                                                <img src="{{ $logo }}" alt="{{ $sponsor->name }}" loading="lazy" class="h-14 w-auto object-contain">
                                            @else
                                                <span class="text-slate-400 font-medium">{{ $sponsor->name }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- CTA BAND --}}
    <section class="bg-[var(--brand-2)]">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-14 text-center text-white">
            <h2 class="text-2xl sm:text-3xl font-bold">{{ __('site.cta_title') }}</h2>
            <p class="mt-2 text-slate-300">{{ __('site.cta_subtitle') }}</p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('author.register') }}" class="btn btn-primary">{{ __('site.submit_paper') }}</a>
                <a href="{{ route('author.registration.create') }}" class="btn btn-ghost">{{ __('site.register_now') }}</a>
            </div>
        </div>
    </section>
</x-layout>
